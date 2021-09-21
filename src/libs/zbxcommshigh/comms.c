/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "comms.h"
#include "zbxjson.h"
#include "log.h"
#include "daemon.h"
#include "zbxalgo.h"
#include "cfg.h"
extern char		*CONFIG_SOURCE_IP;
extern unsigned int	configured_tls_connect_mode;

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
extern char	*CONFIG_TLS_SERVER_CERT_ISSUER;
extern char	*CONFIG_TLS_SERVER_CERT_SUBJECT;
extern char	*CONFIG_TLS_PSK_IDENTITY;
#endif

static int	zbx_tcp_connect_failover(zbx_socket_t *s, const char *source_ip, zbx_vector_ptr_t *addrs,
		int timeout, unsigned int tls_connect, const char *tls_arg1, const char *tls_arg2)
{
	int	ret, i;

	for (i = 0; i < addrs->values_num; i++)
	{
		zbx_addr_t	*addr;

		addr = (zbx_addr_t *)addrs->values[0];

		if (FAIL != (ret = zbx_tcp_connect(s, source_ip, addr->ip, addr->port, timeout, tls_connect, tls_arg1,
				tls_arg2)))
		{
			if (0 != i)
			{
				zabbix_log(LOG_LEVEL_WARNING, "Connected to the alternative server [%s]:%d",
						((zbx_addr_t *)addrs->values[0])->ip,
						((zbx_addr_t *)addrs->values[0])->port);
			}

			break;
		}

		zabbix_log(LOG_LEVEL_WARNING, "Unable to connect to the server [%s]:%d [%s]",
				((zbx_addr_t *)addrs->values[0])->ip, ((zbx_addr_t *)addrs->values[0])->port,
				zbx_socket_strerror());

		zbx_vector_ptr_remove(addrs, 0);
		zbx_vector_ptr_append(addrs, addr);
	}

	return ret;
}

int	connect_to_server(zbx_socket_t *sock, zbx_vector_ptr_t *addrs, int timeout, int retry_interval)
{
	int	res, lastlogtime, now;
	char	*tls_arg1, *tls_arg2;

	zabbix_log(LOG_LEVEL_DEBUG, "In connect_to_server() [%s]:%d [timeout:%d]",
			((zbx_addr_t *)addrs->values[0])->ip, ((zbx_addr_t *)addrs->values[0])->port, timeout);

	switch (configured_tls_connect_mode)
	{
		case ZBX_TCP_SEC_UNENCRYPTED:
			tls_arg1 = NULL;
			tls_arg2 = NULL;
			break;
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		case ZBX_TCP_SEC_TLS_CERT:
			tls_arg1 = CONFIG_TLS_SERVER_CERT_ISSUER;
			tls_arg2 = CONFIG_TLS_SERVER_CERT_SUBJECT;
			break;
		case ZBX_TCP_SEC_TLS_PSK:
			tls_arg1 = CONFIG_TLS_PSK_IDENTITY;
			tls_arg2 = NULL;	/* zbx_tls_connect() will find PSK */
			break;
#endif
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
	}

	if (FAIL == (res = zbx_tcp_connect_failover(sock, CONFIG_SOURCE_IP, addrs, timeout,
			configured_tls_connect_mode, tls_arg1, tls_arg2)))
	{
		if (0 != retry_interval)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Could not to connect to server. Will retry every %d second(s)",
					retry_interval);

			lastlogtime = (int)time(NULL);

			while (ZBX_IS_RUNNING() && FAIL == (res = zbx_tcp_connect_failover(sock, CONFIG_SOURCE_IP,
					addrs, timeout, configured_tls_connect_mode, tls_arg1, tls_arg2)))
			{
				now = (int)time(NULL);

				if (LOG_ENTRY_INTERVAL_DELAY <= now - lastlogtime)
				{
					zabbix_log(LOG_LEVEL_WARNING, "Still unable to connect...");
					lastlogtime = now;
				}

				sleep(retry_interval);
			}

			if (FAIL != res)
				zabbix_log(LOG_LEVEL_WARNING, "Connection restored.");
		}
	}

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_send_response                                                *
 *                                                                            *
 * Purpose: send json SUCCEED or FAIL to socket along with an info message    *
 *                                                                            *
 * Parameters: sock     - [IN] socket descriptor                              *
 *             result   - [IN] SUCCEED or FAIL                                *
 *             info     - [IN] info message (optional)                        *
 *             version  - [IN] the version data (optional)                    *
 *             protocol - [IN] the transport protocol                         *
 *             timeout - [IN] timeout for this operation                      *
 *                                                                            *
 * Return value: SUCCEED - data successfully transmitted                      *
 *               NETWORK_ERROR - network related error occurred               *
 *                                                                            *
 * Author: Alexander Vladishev, Alexei Vladishev                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_send_response_ext(zbx_socket_t *sock, int result, const char *info, const char *version, int protocol,
		int timeout)
{
	struct zbx_json	json;
	const char	*resp;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	resp = SUCCEED == result ? ZBX_PROTO_VALUE_SUCCESS : ZBX_PROTO_VALUE_FAILED;

	zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, resp, ZBX_JSON_TYPE_STRING);

	if (NULL != info && '\0' != *info)
		zbx_json_addstring(&json, ZBX_PROTO_TAG_INFO, info, ZBX_JSON_TYPE_STRING);

	if (NULL != version)
		zbx_json_addstring(&json, ZBX_PROTO_TAG_VERSION, version, ZBX_JSON_TYPE_STRING);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() '%s'", __func__, json.buffer);

	if (FAIL == (ret = zbx_tcp_send_ext(sock, json.buffer, strlen(json.buffer), 0, protocol, timeout)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error sending result back: %s", zbx_socket_strerror());
		ret = NETWORK_ERROR;
	}

	zbx_json_free(&json);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_recv_response                                                *
 *                                                                            *
 * Purpose: read a response message (in JSON format) from socket, optionally  *
 *          extract "info" value.                                             *
 *                                                                            *
 * Parameters: sock    - [IN] socket descriptor                               *
 *             timeout - [IN] timeout for this operation                      *
 *             error   - [OUT] pointer to error message                       *
 *                                                                            *
 * Return value: SUCCEED - "response":"success" successfully retrieved        *
 *               FAIL    - otherwise                                          *
 * Comments:                                                                  *
 *     Allocates memory.                                                      *
 *                                                                            *
 *     If an error occurs, the function allocates dynamic memory for an error *
 *     message and writes its address into location pointed to by "error"     *
 *     parameter.                                                             *
 *                                                                            *
 *     When the "info" value is present in the response message then function *
 *     copies the "info" value into the "error" buffer as additional          *
 *     information                                                            *
 *                                                                            *
 *     IMPORTANT: it is a responsibility of the caller to release the         *
 *                "error" memory !                                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_recv_response(zbx_socket_t *sock, int timeout, char **error)
{
	struct zbx_json_parse	jp;
	char			value[16];
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_tcp_recv_to(sock, timeout))
	{
		/* since we have successfully sent data earlier, we assume the other */
		/* side is just too busy processing our data if there is no response */
		*error = zbx_strdup(*error, zbx_socket_strerror());
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() '%s'", __func__, sock->buffer);

	/* deal with empty string here because zbx_json_open() does not produce an error message in this case */
	if ('\0' == *sock->buffer)
	{
		*error = zbx_strdup(*error, "empty string received");
		goto out;
	}

	if (SUCCEED != zbx_json_open(sock->buffer, &jp))
	{
		*error = zbx_strdup(*error, zbx_json_strerror());
		goto out;
	}

	if (SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, value, sizeof(value), NULL))
	{
		*error = zbx_strdup(*error, "no \"" ZBX_PROTO_TAG_RESPONSE "\" tag");
		goto out;
	}

	if (0 != strcmp(value, ZBX_PROTO_VALUE_SUCCESS))
	{
		char	*info = NULL;
		size_t	info_alloc = 0;

		if (SUCCEED == zbx_json_value_by_name_dyn(&jp, ZBX_PROTO_TAG_INFO, &info, &info_alloc, NULL))
			*error = zbx_strdup(*error, info);
		else
			*error = zbx_dsprintf(*error, "negative response \"%s\"", value);
		zbx_free(info);
		goto out;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

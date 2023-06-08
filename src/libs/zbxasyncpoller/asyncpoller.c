/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "asyncpoller.h"
#include "zbxtime.h"
#include "log.h"

#ifdef HAVE_LIBEVENT
#	include <event.h>
#endif

typedef struct
{
	void		*data;

	zbx_async_task_process_cb_t	process_cb;
	zbx_async_task_clear_cb_t	free_cb;

	struct event	*tx_event;
	struct event	*rx_event;
	struct event	*timeout_event;
}
zbx_async_task_t;

static void	async_task_remove(zbx_async_task_t *task)
{
	printf("remove async task\n");
	task->free_cb(task->data);

	event_free(task->rx_event);
	event_free(task->tx_event);
	event_free(task->timeout_event);

	zbx_free(task);
}

static void	async_event(evutil_socket_t fd, short what, void *arg)
{
	zbx_async_task_t	*task = (zbx_async_task_t *)arg;
	int			ret;

	ZBX_UNUSED(fd);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (ZBX_ASYNC_TASK_STOP == (ret = task->process_cb(what, task->data)))
		async_task_remove(task);

	if (ZBX_ASYNC_TASK_READ == ret)
		event_add(task->rx_event, NULL);

	if (ZBX_ASYNC_TASK_WRITE == ret)
		event_add(task->tx_event, NULL);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	zbx_async_poller_add_task(struct event_base *ev, int fd, void *data, int timeout,
		zbx_async_task_process_cb_t process_cb, zbx_async_task_clear_cb_t clear_cb)
{
	zbx_async_task_t	*task;
	struct timeval		tv = {timeout, 0};

	task = (zbx_async_task_t *)zbx_malloc(NULL, sizeof(zbx_async_task_t));
	task->data = data;
	task->process_cb = process_cb;
	task->free_cb = clear_cb;
	task->timeout_event = evtimer_new(ev,  async_event, (void *)task);

	evtimer_add(task->timeout_event, &tv);

	task->rx_event = event_new(ev, fd, EV_READ, async_event, (void *)task);
	task->tx_event = event_new(ev, fd, EV_WRITE, async_event, (void *)task);

	/* call initialization event */
	async_event(fd, 0, task);
}

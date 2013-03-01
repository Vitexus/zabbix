<?php
/*
** Zabbix
** Copyright (C) 2000-2013 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

define('GRAPH_GOOD', 0);
define('GRAPH_BAD', 1);

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 */
class testInheritanceGraphPrototype extends CWebTest {

	/**
	 * The name of the test template created in the test data set.
	 *
	 * @var string
	 */
	protected $template = 'Inheritance test template';

	/**
	 * The name of the test host created in the test data set.
	 *
	 * @var string
	 */
	protected $host = 'Template inheritance test host';

	/**
	 * The name of the test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $discoveryRule = 'discoveryRuleTest';

	/**
	 * The name of the test discovery rule key created in the test data set.
	 *
	 * @var string
	 */
	protected $discoveryKey = 'discovery-rule-test';

	/**
	 * The name of the test item created in the test data set.
	 *
	 * @var string
	 */
	protected $itemSimple = 'itemInheritance';

	/**
	 * The name of the test item key created in the test data set.
	 *
	 * @var string
	 */
	protected $itemKeySimple = 'key-item-inheritance';

	/**
	 * The name of the test item prototype within test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $item = 'itemDiscovery';

	/**
	 * The name of the test item prototype key within test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $itemKey = 'item-discovery-prototype';


	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testInheritanceGraphPrototype_setup() {
		DBsave_tables('hosts');
	}

	// returns all possible item types
	public static function graphTypes() {
		return array(
			array(
				array(
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Item',
				)
			),
			array(
				array(
					'graphtype' => 'Normal',
				)
			),
			array(
				array(
					'graphtype' => 'Stacked',
				)
			),
			array(
				array(
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed' ,
				)
			),
			array(
				array(
					'graphtype' => 'Stacked',
					'ymin_type' => 'Item',
					'ymax_type' => 'Fixed',
				)
			),
			array(
				array(
					'graphtype' => 'Stacked',
					'ymin_type' => 'Item',
					'ymax_type' => 'Item',
				)
			),
			array(
				array(
					'graphtype' => 'Pie',
				)
			),
			array(
				array(
					'graphtype' => 'Exploded',
				)
			)
		);
	}

	/**
	 * @dataProvider graphTypes
	 */
	public function testInheritanceGraphPrototype_CheckLayout($data) {

		$this->zbxTestLogin('templates.php');
		$this->zbxTestClickWait('link='.$this->template);
		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('link='.$this->discoveryRule);
		$this->zbxTestClickWait('link=Graph prototypes');

	//	$this->checkTitle('Configuration of graph prototypes');
		$this->checkTitle('Configuration of graphs');
		$this->zbxTestTextPresent(array('CONFIGURATION OF GRAPH PROTOTYPES', "Graph prototypes of ".$this->discoveryRule));

		$this->zbxTestClickWait('form');
	// $this->checkTitle('Configuration of graph prototypes');
		$this->checkTitle('Configuration of graphs');
		$this->zbxTestTextPresent(array('CONFIGURATION OF GRAPH PROTOTYPES', 'Graph prototype'));

		$this->zbxTestTextPresent('Name');
		$this->assertVisible('name');
		$this->assertAttribute("//input[@id='name']/@maxlength", '255');
		$this->assertAttribute("//input[@id='name']/@size", '50');
		$this->assertAttribute("//input[@id='name']/@autofocus", 'autofocus');

		$this->zbxTestTextPresent('Width');
		$this->assertVisible('width');
		$this->assertAttribute("//input[@id='width']/@maxlength", '5');
		$this->assertAttribute("//input[@id='width']/@size", '5');
		$this->assertAttribute("//input[@id='width']/@value", '900');

		$this->zbxTestTextPresent('Height');
		$this->assertVisible('height');
		$this->assertAttribute("//input[@id='height']/@maxlength", '5');
		$this->assertAttribute("//input[@id='height']/@size", '5');
		$this->assertAttribute("//input[@id='height']/@value", '200');

		$this->zbxTestTextPresent('Graph type');
		$this->assertVisible('graphtype');
		$this->zbxTestDropdownHasOptions('graphtype', array(
			'Normal',
			'Stacked',
			'Pie',
			'Exploded'
		));
		$this->assertAttribute("//*[@id='graphtype']/option[text()='Normal']/@selected", 'selected');

		if (isset($data['graphtype'])) {
			$this->zbxTestDropdownSelectWait('graphtype', $data['graphtype']);
		}
		$graphtype = $this->getSelectedLabel('graphtype');

		if (isset($data['ymin_type'])) {
			$this->zbxTestDropdownSelectWait('ymin_type', $data['ymin_type']);
		}

		if (isset($data['ymax_type'])) {
			$this->zbxTestDropdownSelectWait('ymax_type', $data['ymax_type']);
		}

		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {
			$ymin_type = $this->getSelectedLabel('ymin_type');
			$ymax_type = $this->getSelectedLabel('ymax_type');
		}
		else {
			$ymin_type = null;
			$ymax_type = null;
		}

		$this->zbxTestTextPresent('Show legend');
		$this->assertVisible('legend');
		$this->assertAttribute("//*[@id='legend']/@checked", 'checked');

		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {
			$this->zbxTestTextPresent('Show working time');
			$this->assertVisible('showworkperiod');
			$this->assertAttribute("//*[@id='showworkperiod']/@checked", 'checked');
		}
		else {
			$this->zbxTestTextNotPresent('Show working time');
			$this->assertElementNotPresent('showworkperiod');
		}

		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {
			$this->zbxTestTextPresent('Show triggers');
			$this->assertVisible('showtriggers');
			$this->assertAttribute("//*[@id='showtriggers']/@checked", 'checked');
		}
		else {
			$this->zbxTestTextNotPresent('Show triggers');
			$this->assertElementNotPresent('showtriggers');
		}

		if ($graphtype == 'Normal') {
			$this->zbxTestTextPresent('Percentile line (left)');
			$this->assertVisible('visible_percent_left');

			$this->zbxTestTextPresent('Percentile line (right)');
			$this->assertVisible('visible_percent_right');
		}
		else {
			$this->zbxTestTextNotPresent('Percentile line (left)');
			$this->assertElementNotPresent('visible_percent_left');

			$this->zbxTestTextNotPresent('Percentile line (right)');
			$this->assertElementNotPresent('visible_percent_right');
		}

		if ($graphtype == 'Pie' || $graphtype == 'Exploded') {
			$this->zbxTestTextPresent('3D view');
			$this->assertVisible('graph3d');
		}
		else {
			$this->zbxTestTextNotPresent('3D view');
			$this->assertElementNotPresent('graph3d');
		}

		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {
			$this->zbxTestTextPresent('Y axis MIN value');
			$this->assertVisible('ymin_type');
			$this->zbxTestDropdownHasOptions('ymin_type', array(
				'Calculated',
				'Fixed',
				'Item'
			));
			switch ($ymin_type) {
				case 'Calculated':
					$this->assertAttribute("//*[@id='ymin_type']/option[text()='$ymin_type']/@selected", 'selected');
					break;
				case 'Fixed':
					$this->assertAttribute("//*[@id='ymin_type']/option[text()='$ymin_type']/@selected", 'selected');
					break;
				case 'Item':
					$this->assertAttribute("//*[@id='ymin_type']/option[text()='$ymin_type']/@selected", 'selected');
					break;
			}

			$this->zbxTestTextPresent('Y axis MAX value');
			$this->assertVisible('ymax_type');
			$this->zbxTestDropdownHasOptions('ymax_type', array(
				'Calculated',
				'Fixed',
				'Item'
			));
			switch ($ymax_type) {
				case 'Calculated':
					$this->assertAttribute("//*[@id='ymax_type']/option[text()='$ymax_type']/@selected", 'selected');
					break;
				case 'Fixed':
					$this->assertAttribute("//*[@id='ymax_type']/option[text()='$ymax_type']/@selected", 'selected');
					break;
				case 'Item':
					$this->assertAttribute("//*[@id='ymax_type']/option[text()='$ymax_type']/@selected", 'selected');
					break;
			}
		}
		else {
			$this->zbxTestTextNotPresent('Y axis MIN value');
			$this->assertElementNotPresent('ymin_type');

			$this->zbxTestTextNotPresent('Y axis MAX value');
			$this->assertElementNotPresent('ymax_type');
		}

		switch($ymin_type) {
			case 'Fixed':
				$this->assertVisible('yaxismin');
				$this->assertAttribute("//input[@id='yaxismin']/@maxlength", '255');
				$this->assertAttribute("//input[@id='yaxismin']/@size", '7');
				$this->assertAttribute("//input[@id='yaxismin']/@value", '0.00');

				$this->assertElementNotPresent('ymin_name');
				$this->assertElementNotPresent('yaxis_min');
				break;
			case 'Calculated':
				$this->assertElementNotPresent('ymin_name');
				$this->assertElementNotPresent('yaxis_min');
				$this->assertNotVisible('yaxismin');
				break;
			case 'Item':
				$this->zbxTestLaunchPopup('add_item');
				$this->zbxTestClick('link='.$this->itemSimple);
				sleep(1);
				$this->selectWindow(null);

				$this->zbxTestLaunchPopup('add_protoitem');
				$this->zbxTestClick('link='.$this->item);
				sleep(1);
				$this->selectWindow(null);

				$this->zbxTestDropdownSelectWait('ymin_type', 'Calculated');
				$this->zbxTestDropdownSelectWait('ymin_type', 'Item');
				$this->assertElementPresent('ymin_name');
				$this->assertElementPresent('yaxis_min');
//*[@id="yaxis_min"]
				$this->assertAttribute("//input[@id='yaxis_min']/@value", 'Select ');
				$this->assertAttribute("//input[@id='yaxis_min']/@value", 'Select prototype');

				$this->assertNotVisible('yaxismin');
				break;
			default:
				$this->zbxTestTextNotPresent('Add graph items first');
				$this->assertElementNotPresent('ymin_name');
				$this->assertElementNotPresent('yaxis_min');
				$this->assertElementNotPresent('yaxismin');
				break;
		}

	/*	switch($ymax_type) {
			case 'Fixed':
				$this->assertVisible('yaxismax');
				$this->assertAttribute("//input[@id='yaxismax']/@maxlength", '255');
				$this->assertAttribute("//input[@id='yaxismax']/@size", '7');
				$this->assertAttribute("//input[@id='yaxismax']/@value", '100.00');

				$this->assertElementNotPresent('ymax_name');
				$this->assertElementNotPresent('yaxis_max');
				break;
			case 'Calculated':
				$this->assertElementNotPresent('ymax_name');
				$this->assertElementNotPresent('yaxis_max');
				$this->assertNotVisible('yaxismax');
				break;
			case 'Item':
				$this->zbxTestLaunchPopup('add_item');
				$this->zbxTestClick("link=Host name");
				sleep(1);
				$this->selectWindow(null);

				$this->zbxTestDropdownSelectWait('ymax_type', 'Calculated');
				$this->zbxTestDropdownSelectWait('ymax_type', 'Item');
				$this->assertElementPresent('ymax_name');
				$this->assertElementPresent('yaxis_max');
				$this->assertAttribute("//input[@id='yaxis_max']/@value", 'Select');

				$this->assertNotVisible('yaxismax');
				break;
			default:
				$this->zbxTestTextNotPresent('Add graph items first');
				$this->assertElementNotPresent('ymax_name');
				$this->assertElementNotPresent('yaxis_max');
				$this->assertElementNotPresent('yaxismax');
				break;
		}

	*/	switch ($graphtype) {
			case 'Normal':
				$this->zbxTestTextPresent(array('Items', 'Name', 'Function', 'Draw style', 'Y axis side', 'Colour', 'Action'));
				break;
			case 'Stacked':
				$this->zbxTestTextPresent(array('Items', 'Name', 'Function', 'Y axis side', 'Colour', 'Action'));
				break;
			case 'Pie':
			case 'Exploded':
				$this->zbxTestTextPresent(array('Items', 'Name', 'Type', 'Function', 'Colour', 'Action'));
				break;
		}

		$this->assertVisible('add_item');
		$this->assertAttribute("//input[@id='add_item']/@value", 'Add');

		$this->assertVisible('add_protoitem');
		$this->assertAttribute("//input[@id='add_protoitem']/@value", 'Add prototype');

		$this->zbxTestClick('link=Preview');

		$this->assertVisible('save');
		$this->assertAttribute("//input[@id='save']/@value", 'Save');

		$this->assertVisible('cancel');
		$this->assertAttribute("//input[@id='cancel']/@value", 'Cancel');
	}


/*	public static function simple() {
		return array(
			array(
				array('expected' => GRAPH_GOOD,
					'graphName' => 'graphSimple',
					'hostCheck' => true,
					'dbCheck' => true)
			),
			array(
				array('expected' => GRAPH_GOOD,
					'graphName' => 'graphName',
					'hostCheck' => true)
			),
			array(
				array('expected' => GRAPH_GOOD,
					'graphName' => 'graphRemove',
					'hostCheck' => true,
					'dbCheck' => true,
					'remove' => true)
			),
			array(
				array('expected' => GRAPH_GOOD,
					'graphName' => 'graphNotRemove',
					'hostCheck' => true,
					'dbCheck' => true,
					'hostRemove' => true,
					'remove' => true)
			),
			array(
				array('expected' => GRAPH_GOOD,
					'graphName' => 'graphSomeRemove',
					'hostCheck' => true,
					'dbCheck' => true,
					'hostRemove' => true,
					'remove' => true)
			),
			array(
				array('expected' => GRAPH_BAD,
					'graphName' => 'graphSimple',
					'errors' => array(
						'ERROR: Cannot add graph',
						'Graph with name "graphSimple" already exists in graphs or graph prototypes')
				)
			)
		);
	}
*/
	/**
	 * @dataProvider simple
	 */
/*	public function testInheritanceGraphPrototype_simpleCreate($data) {
		$this->zbxTestLogin('templates.php');

		$template = 'Inheritance test template';
		$host = 'Template inheritance test host';

		$itemName = 'itemInheritance';
		$graphName = $data['graphName'];

		$this->zbxTestClickWait("link=$template");
		$this->zbxTestClickWait("//div[@class='w']//a[text()='Graphs']");
		$this->zbxTestClickWait('form');

		$this->input_type('name', $graphName);
		$this->zbxTestLaunchPopup('add_item');
		$this->zbxTestClick("link=$itemName");
		$this->selectWindow(null);
		sleep(1);

		$this->zbxTestClickWait('save');

		switch ($data['expected']) {
			case GRAPH_GOOD:
				$this->zbxTestTextPresent('Graph added');
				$this->checkTitle('Configuration of graphs');
				$this->zbxTestTextPresent('CONFIGURATION OF GRAPHS');
				break;

			case GRAPH_BAD:
				$this->checkTitle('Configuration of graphs');
				$this->zbxTestTextPresent('CONFIGURATION OF GRAPHS');
				foreach ($data['errors'] as $msg) {
					$this->zbxTestTextPresent($msg);
				}
				break;
		}

		if (isset($data['hostCheck'])) {
			$this->zbxTestOpenWait('hosts.php');
			$this->zbxTestClickWait("link=$host");
			$this->zbxTestClickWait("//div[@class='w']//a[text()='Graphs']");

			$this->zbxTestTextPresent("$template: $graphName");
			$this->zbxTestClickWait("link=$graphName");

			$this->assertElementValue('name', $graphName);
			$this->assertElementPresent("//span[text()='$host: $itemName']");
		}

		if (isset($data['dbCheck'])) {
			// template
			$result = DBselect("SELECT name, graphid FROM graphs where name = '".$graphName."' limit 1");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $graphName);
				$templateid = $row['graphid'];
			}

			// host
			$result = DBselect("SELECT name FROM graphs where name = '".$graphName."' AND templateid = ".$templateid."");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $graphName);
			}
		}

		if (isset($data['hostRemove'])) {
			$result = DBselect("SELECT graphid FROM graphs where name = '".$graphName."' limit 1");
			while ($row = DBfetch($result)) {
				$templateid = $row['graphid'];
			}

			$result = DBselect("SELECT graphid FROM graphs where name = '".$graphName."' AND templateid = ".$templateid."");
			while ($row = DBfetch($result)) {
				$graphId = $row['graphid'];
			}
			$this->zbxTestOpenWait('hosts.php');
			$this->zbxTestClickWait("link=$host");
			$this->zbxTestClickWait("//div[@class='w']//a[text()='Graphs']");

			$this->assertElementPresent("group_graphid_$graphId");
			$this->assertAttribute("//input[@id='group_graphid_$graphId']/@disabled", 'disabled');
		}

		if (isset($data['remove'])) {
			$result = DBselect("SELECT graphid FROM graphs where name = '".$graphName."' limit 1");
			while ($row = DBfetch($result)) {
				$graphid = $row['graphid'];
			}

			$this->zbxTestOpenWait('templates.php');
			$this->zbxTestClickWait("link=$template");
			$this->zbxTestClickWait("//div[@class='w']//a[text()='Graphs']");

			$this->zbxTestCheckboxSelect("group_graphid_$graphid");
			$this->zbxTestDropdownSelect('go', 'Delete selected');
			$this->zbxTestClick('goButton');

			$this->getConfirmation();
			$this->wait();
			$this->zbxTestTextPresent('Graphs deleted');
			$this->zbxTestTextNotPresent("$template: $graphName");
		}
	}
*/
	/**
	 * Restore the original tables.
	 */
	public function testInheritanceGraphPrototype_teardown() {
		DBrestore_tables('hosts');
	}
}

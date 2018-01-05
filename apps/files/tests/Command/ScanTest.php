<?php
/**
 * @author Sujith Haridasan <sharidasan@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files\Tests\Command;

use OCA\Files\Command\Scan;
use Symfony\Component\Console\Tester\CommandTester;
use Test\TestCase;
use Test\Traits\UserTrait;

/**
 * Class ScanTest
 *
 * @group DB
 * @package OCA\Files\Tests\Command
 */
class ScanTest extends TestCase {
	use UserTrait;

	/** @var  CommandTester */
	private $commandTester;
	protected function setUp() {
		parent::setUp();
		$command = new Scan(
			\OC::$server->getUserManager(), \OC::$server->getGroupManager(),
			\OC::$server->getLockingProvider(), \OC::$server->getMimeTypeLoader(),
			\OC::$server->getConfig());

		$this->commandTester = new CommandTester($command);
		$user1 = $this->createUser('user1');
		$this->createUser('user2');
		\OC::$server->getGroupManager()->createGroup('group1');
		\OC::$server->getGroupManager()->get('group1')->addUser($user1);
	}

	public function dataInput() {
		return [
			[['--groups' => 'haystack'], 'Group name haystack doesn\'t exist'],
			[['--groups' => 'group1'], 'Starting scan for user 1 out of 1 (user1)'],
			[['user_id' => ['user1']], 'Starting scan for user 1 out of 1 (user1)'],
			[['user_id' => ['user2']], 'Starting scan for user 1 out of 1 (user2)']
		];
	}

	/**
	 * @dataProvider dataInput
	 */
	public function testCommandInput($input, $expectedOutput) {
		$this->commandTester->execute($input);
		$output = $this->commandTester->getDisplay();
		$this->assertContains($expectedOutput, $output);
	}

	public function userInputData() {
		return [
			[['--groups' => 'group1'], 'Starting scan for user 1 out of 200']
		];
	}

	/**
	 * @dataProvider userInputData
	 * @param $input
	 * @param $expectedOutput
	 */
	public function testGroupPaginationForUsers($input, $expectedOutput) {
		//First we populate the users
		$user = 'user';
		$numberOfUsersInGroup = 210;
		for($i = 2; $i <= 210; $i++) {
			$userObj = $this->createUser($user.$i);
			\OC::$server->getGroupManager()->get('group1')->addUser($userObj);
		}

		$this->commandTester->execute($input);
		$output = $this->commandTester->getDisplay();
		$this->assertContains($expectedOutput, $output);
		//If pagination works then below assert shouldn't fail
		$this->assertNotContains('Starting scan for user 1 out of 210', $output);
	}
}
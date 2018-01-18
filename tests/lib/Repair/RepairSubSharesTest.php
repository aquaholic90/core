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

namespace Test\Repair;

use OC\Repair\RepairSubShares;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Test\TestCase;
use Test\Traits\UserTrait;

/**
 * Test for repairing invalid sub shares
 *
 * @group  DB
 *
 * @see \OC\Repair\RepairSubShares
 * @package Test\Repair
 */
class RepairSubSharesTest extends TestCase {
	use UserTrait;

	/** @var  \OCP\IDBConnection */
	private $connection;

	/** @var  IRepairStep */
	private $repair;
	protected function setUp() {
		parent::setUp();

		$this->connection = \OC::$server->getDatabaseConnection();
		$this->repair = new RepairSubShares($this->connection);
		$this->createUser('admin');
	}

	protected function tearDown() {
		$this->deleteAllUsersAndGroups();
		$this->deleteAllShares();
		parent::tearDown();
	}

	public function deleteAllUsersAndGroups() {
		$this->tearDownUserTrait();
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('groups')->execute();
		$qb->delete('group_user')->execute();
	}

	public function deleteAllShares() {
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('share')->execute();
	}

	/**
	 * This is a very basic test
	 * This test would populate DB with data
	 * and later, remove the duplicates to test
	 * if the step is working properly
	 */
	public function testPopulateDBAndRemoveDuplicates() {

		$qb = $this->connection->getQueryBuilder();
		//Create 10 users and 3 groups.
		//add 3 users to each group
		$userName = "user";
		$groupName = "group";
		$folderName = "/test";
		$time = time();
		$groupCount = 1;
		for($i = 1; $i <= 10; $i++) {
			$user = $this->createUser($userName.$i);
			if (\OC::$server->getGroupManager()->groupExists($groupName.$groupCount) === false) {
				\OC::$server->getGroupManager()->createGroup($groupName.$groupCount);
			}
			\OC::$server->getGroupManager()->get($groupName.$groupCount)->addUser($user);

			//Create a group share
			$qb->insert('share')
				->values([
					'share_type' => $qb->expr()->literal('2'),
					'share_with' => $qb->expr()->literal($userName.$groupCount),
					'uid_owner' => $qb->expr()->literal('admin'),
					'uid_initiator' => $qb->expr()->literal('admin'),
					'parent' => $qb->expr()->literal(1),
					'item_type' => $qb->expr()->literal('folder'),
					'item_source' => $qb->expr()->literal(24),
					'file_source' => $qb->expr()->literal(24),
					'file_target' => $qb->expr()->literal($folderName.$groupCount),
					'permissions' => $qb->expr()->literal(31),
					'stime' => $qb->expr()->literal($time),
				])
				->execute();

			if (($i%3) === 0) {
				$groupCount++;
				$time = time();
			}
		}

		$outputMock = $this->createMock(IOutput::class);
		$this->repair->run($outputMock);

		$qb = $this->connection->getQueryBuilder();
		$qb->select('id', 'parent', $qb->createFunction('count(*)'))
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(2)))
			->groupBy('parent')
			->addGroupBy('share_with')
			->having('count(*) > 1')->setMaxResults(1000);

		$results = $qb->execute()->fetchAll();
		$this->assertCount(0, $results);
	}

	/**
	 * This is to test large rows i.e, greater than 2000
	 * with duplicates
	 */
	public function testLargeDuplicateShareRows() {
		$qb = $this->connection->getQueryBuilder();
		$userName = "user";
		$time = time();
		$groupCount = 0;
		$folderName = "/test";
		for ($i = 0; $i < 5500; $i++) {
			if (($i % 1000) === 0) {
				$groupCount++;
			}
			$qb->insert('share')
				->values([
					'share_type' => $qb->expr()->literal('2'),
					'share_with' => $qb->expr()->literal($userName.$groupCount),
					'uid_owner' => $qb->expr()->literal('admin'),
					'uid_initiator' => $qb->expr()->literal('admin'),
					'parent' => $qb->expr()->literal(1),
					'item_type' => $qb->expr()->literal('folder'),
					'item_source' => $qb->expr()->literal(24),
					'file_source' => $qb->expr()->literal(24),
					'file_target' => $qb->expr()->literal($folderName.$groupCount),
					'permissions' => $qb->expr()->literal(31),
					'stime' => $qb->expr()->literal($time),
				])
				->execute();
		}

		$outputMock = $this->createMock(IOutput::class);
		$this->repair->run($outputMock);

		$qb = $this->connection->getQueryBuilder();
		$qb->select('id', 'parent', $qb->createFunction('count(*)'))
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(2)))
			->groupBy('parent')
			->addGroupBy('share_with')
			->having('count(*) > 1')->setMaxResults(1000);

		$results = $qb->execute()->fetchAll();
		$this->assertCount(0, $results);
	}
}

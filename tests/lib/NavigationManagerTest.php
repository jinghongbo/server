<?php
/**
 * ownCloud
 *
 * @author Joas Schilling
 * @copyright 2015 Joas Schilling nickvergessen@owncloud.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test;

use OC\App\AppManager;
use OC\NavigationManager;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\L10N\IFactory;

class NavigationManagerTest extends TestCase {
	/** @var AppManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $appManager;
	/** @var IURLGenerator|\PHPUnit_Framework_MockObject_MockObject */
	protected $urlGenerator;
	/** @var IFactory|\PHPUnit_Framework_MockObject_MockObject */
	protected $l10nFac;
	/** @var IUserSession|\PHPUnit_Framework_MockObject_MockObject */
	protected $userSession;
	/** @var IGroupManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $groupManager;
	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	protected $config;

	/** @var \OC\NavigationManager */
	protected $navigationManager;

	protected function setUp() {
		parent::setUp();

		$this->appManager = $this->createMock(AppManager::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->l10nFac = $this->createMock(IFactory::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->config = $this->createMock(IConfig::class);
		$this->navigationManager = new NavigationManager(
			$this->appManager,
			$this->urlGenerator,
			$this->l10nFac,
			$this->userSession,
			$this->groupManager,
			$this->config
		);

		$this->navigationManager->clear(false);
	}

	public function addArrayData() {
		return [
			[
				[
					'id'	=> 'entry id',
					'name'	=> 'link text',
					'order'	=> 1,
					'icon'	=> 'optional',
					'href'	=> 'url',
					'type'	=> 'settings',
				],
				[
					'id'		=> 'entry id',
					'name'		=> 'link text',
					'order'		=> 1,
					'icon'		=> 'optional',
					'href'		=> 'url',
					'active'	=> false,
					'type'		=> 'settings',
				],
			],
			[
				[
					'id'	=> 'entry id',
					'name'	=> 'link text',
					'order'	=> 1,
					//'icon'	=> 'optional',
					'href'	=> 'url',
					'active'	=> true,
				],
				[
					'id'		=> 'entry id',
					'name'		=> 'link text',
					'order'		=> 1,
					'icon'		=> '',
					'href'		=> 'url',
					'active'	=> false,
					'type'		=> 'link',
				],
			],
		];
	}

	/**
	 * @dataProvider addArrayData
	 *
	 * @param array $entry
	 * @param array $expectedEntry
	 */
	public function testAddArray(array $entry, array $expectedEntry) {
		$this->assertEmpty($this->navigationManager->getAll('all'), 'Expected no navigation entry exists');
		$this->navigationManager->add($entry);

		$navigationEntries = $this->navigationManager->getAll('all');
		$this->assertCount(1, $navigationEntries, 'Expected that 1 navigation entry exists');
		$this->assertEquals($expectedEntry, $navigationEntries[0]);

		$this->navigationManager->clear(false);
		$this->assertEmpty($this->navigationManager->getAll('all'), 'Expected no navigation entry exists after clear()');
	}

	/**
	 * @dataProvider addArrayData
	 *
	 * @param array $entry
	 * @param array $expectedEntry
	 */
	public function testAddClosure(array $entry, array $expectedEntry) {
		global $testAddClosureNumberOfCalls;
		$testAddClosureNumberOfCalls = 0;

		$this->navigationManager->add(function () use ($entry) {
			global $testAddClosureNumberOfCalls;
			$testAddClosureNumberOfCalls++;

			return $entry;
		});

		$this->assertEquals(0, $testAddClosureNumberOfCalls, 'Expected that the closure is not called by add()');

		$navigationEntries = $this->navigationManager->getAll('all');
		$this->assertEquals(1, $testAddClosureNumberOfCalls, 'Expected that the closure is called by getAll()');
		$this->assertCount(1, $navigationEntries, 'Expected that 1 navigation entry exists');
		$this->assertEquals($expectedEntry, $navigationEntries[0]);

		$navigationEntries = $this->navigationManager->getAll('all');
		$this->assertEquals(1, $testAddClosureNumberOfCalls, 'Expected that the closure is only called once for getAll()');
		$this->assertCount(1, $navigationEntries, 'Expected that 1 navigation entry exists');
		$this->assertEquals($expectedEntry, $navigationEntries[0]);

		$this->navigationManager->clear(false);
		$this->assertEmpty($this->navigationManager->getAll('all'), 'Expected no navigation entry exists after clear()');
	}

	public function testAddArrayClearGetAll() {
		$entry = [
			'id'	=> 'entry id',
			'name'	=> 'link text',
			'order'	=> 1,
			'icon'	=> 'optional',
			'href'	=> 'url',
		];

		$this->assertEmpty($this->navigationManager->getAll(), 'Expected no navigation entry exists');
		$this->navigationManager->add($entry);
		$this->navigationManager->clear(false);
		$this->assertEmpty($this->navigationManager->getAll(), 'Expected no navigation entry exists after clear()');
	}

	public function testAddClosureClearGetAll() {
		$this->assertEmpty($this->navigationManager->getAll(), 'Expected no navigation entry exists');

		$entry = [
			'id'	=> 'entry id',
			'name'	=> 'link text',
			'order'	=> 1,
			'icon'	=> 'optional',
			'href'	=> 'url',
		];

		global $testAddClosureNumberOfCalls;
		$testAddClosureNumberOfCalls = 0;

		$this->navigationManager->add(function () use ($entry) {
			global $testAddClosureNumberOfCalls;
			$testAddClosureNumberOfCalls++;

			return $entry;
		});

		$this->assertEquals(0, $testAddClosureNumberOfCalls, 'Expected that the closure is not called by add()');
		$this->navigationManager->clear(false);
		$this->assertEquals(0, $testAddClosureNumberOfCalls, 'Expected that the closure is not called by clear()');
		$this->assertEmpty($this->navigationManager->getAll(), 'Expected no navigation entry exists after clear()');
		$this->assertEquals(0, $testAddClosureNumberOfCalls, 'Expected that the closure is not called by getAll()');
	}

	/**
	 * @dataProvider providesNavigationConfig
	 */
	public function testWithAppManager($expected, $navigation, $isAdmin = false) {

		$l = $this->createMock(IL10N::class);
		$l->expects($this->any())->method('t')->willReturnCallback(function($text, $parameters = []) {
			return vsprintf($text, $parameters);
		});

		$this->appManager->expects($this->once())->method('getInstalledApps')->willReturn(['test']);
		$this->appManager->expects($this->once())->method('getAppInfo')->with('test')->willReturn($navigation);
		$this->l10nFac->expects($this->exactly(count($expected) + 1))->method('get')->willReturn($l);
		$this->urlGenerator->expects($this->any())->method('imagePath')->willReturnCallback(function($appName, $file) {
			return "/apps/$appName/img/$file";
		});
		$this->urlGenerator->expects($this->exactly(count($expected)))->method('linkToRoute')->willReturnCallback(function() {
			return "/apps/test/";
		});
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())->method('getUID')->willReturn('user001');
		$this->userSession->expects($this->any())->method('getUser')->willReturn($user);
		$this->groupManager->expects($this->any())->method('isAdmin')->willReturn($isAdmin);

		$this->navigationManager->clear();
		$entries = $this->navigationManager->getAll('all');
		$this->assertEquals($expected, $entries);
	}

	public function providesNavigationConfig() {
		return [
			'minimalistic' => [[[
				'id' => 'test',
				'order' => 100,
				'href' => '/apps/test/',
				'icon' => '/apps/test/img/app.svg',
				'name' => 'Test',
				'active' => false,
				'type' => 'link',
			]], ['navigations' => [['route' => 'test.page.index', 'name' => 'Test']]]],
			'minimalistic-settings' => [[[
				'id' => 'test',
				'order' => 100,
				'href' => '/apps/test/',
				'icon' => '/apps/test/img/app.svg',
				'name' => 'Test',
				'active' => false,
				'type' => 'settings',
			]], ['navigations' => [['route' => 'test.page.index', 'name' => 'Test', 'type' => 'settings']]]],
			'no admin' => [[[
				'id' => 'test',
				'order' => 100,
				'href' => '/apps/test/',
				'icon' => '/apps/test/img/app.svg',
				'name' => 'Test',
				'active' => false,
				'type' => 'link',
			]], ['navigations' => [['@attributes' => ['role' => 'admin'], 'route' => 'test.page.index', 'name' => 'Test']]], true],
			'no name' => [[], ['navigations' => [['@attributes' => ['role' => 'admin'], 'route' => 'test.page.index']]], true],
			'admin' => [[], ['navigations' => [['@attributes' => ['role' => 'admin'], 'route' => 'test.page.index', 'name' => 'Test']]]]
		];
	}
}
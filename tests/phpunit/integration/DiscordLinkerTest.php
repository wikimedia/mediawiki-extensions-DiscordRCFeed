<?php

namespace MediaWiki\Extension\DiscordRCFeed\Tests\Integration;

use MediaWiki\Extension\DiscordRCFeed\DiscordLinker;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWikiIntegrationTestCase;
use Title;
use User;
use Wikimedia\TestingAccessWrapper;

/**
 * @group DiscordRCFeed
 * @group Database
 *
 * @covers \MediaWiki\Extension\DiscordRCFeed\DiscordLinker
 */
class DiscordLinkerTest extends MediaWikiIntegrationTestCase {

	/** @var TestingAccessWrapper */
	private $wrapper;

	protected function setUp(): void {
		parent::setUp();
		$renderer = new DiscordLinker();
		$this->wrapper = TestingAccessWrapper::newFromObject( $renderer );
	}

	public static function providerUserText() {
		return [
			'should disable user tools' => [
				'[Foo](https://foo.bar/index.php/User:Foo)',
				[],
				[],
				'Foo',
			],
			'should urlencode special characters' => [
				'[Foo&bar](https://foo.bar/index.php/User:Foo%26bar)',
				[],
				[],
				'Foo&bar',
			],
			'should render user tools' => [
				'[Foo](https://foo.bar/index.php/User:Foo) '
				. '([IP Block](https://foo.bar/index.php/Special:Block/Foo))',
				[],
				[
					[
						'target' => 'special',
						'special' => 'Block',
						'text' => 'IP Block'
					]
				],
				'Foo',
			],
			'"talk" target should be shown' => [
				'[Foo](https://foo.bar/index.php/User:Foo) '
				. '([Talk](https://foo.bar/index.php/User_talk:Foo))',
				[],
				[
					[
						'target' => 'talk',
						'text' => 'Talk'
					]
				],
				'Foo',
			],
			'"user_page" should be omitted in the block style' => [
				'[Foo](https://foo.bar/index.php/User:Foo)',
				[],
				[
					[
						'target' => 'user_page',
						'text' => 'User page'
					]
				],
				'Foo',
			],
			'User tools should be in the content language' => [
				'[Foo](https://foo.bar/index.php/Usuario:Foo) '
				. '([IP Block](https://foo.bar/index.php/Especial:Bloquear/Foo))',
				[ 'wgLanguageCode' => 'es' ],
				[
					[
						'target' => 'special',
						'special' => 'Block',
						'text' => 'IP Block'
					]
				],
				'Foo',
			]
		];
	}

	/**
	 * @dataProvider providerUserText
	 * @covers \MediaWiki\Extension\DiscordRCFeed\DiscordLinker::makeUserTextWithTools
	 */
	public function testMakeUserTextWithTools(
		string $expected,
		array $globals,
		array $userTools,
		string $name
	) {
		$globals += [
			'wgServer' => 'https://foo.bar',
			'wgArticlePath' => '/index.php/$1',
		];
		$this->setMwGlobals( $globals );
		$linkRenderer = new DiscordLinker( $userTools );
		$user = new User();
		$user->setName( $name );
		$user->addToDatabase();
		$actual = $linkRenderer->makeUserTextWithTools( $user );
		$this->assertSame( $expected, $actual );
	}

	public static function providerUserTools() {
		return [
			'should render link to user page' => [
				'[User page](https://foo.bar/index.php/User:Foo)',
				[
					[
						'target' => 'user_page',
						'text' => 'User page'
					]
				],
				'Foo',
			],
		];
	}

	/**
	 * @dataProvider providerUserTools
	 * @covers \MediaWiki\Extension\DiscordRCFeed\DiscordLinker::makeUserTools
	 */
	public function testMakeUserTools(
		string $expected,
		array $userTools,
		string $name
	) {
		$this->setMwGlobals( [
			'wgServer' => 'https://foo.bar',
			'wgArticlePath' => '/index.php/$1',
		] );
		$linkRenderer = new DiscordLinker( $userTools );
		$user = new User();
		$user->setName( $name );
		$user->addToDatabase();
		$actual = $linkRenderer->makeUserTools( $user, ' ', true );
		$this->assertSame( $expected, $actual );
	}

	public static function providerDiscordPageText(): array {
		$view = [
			'target' => 'view',
			'msg' => 'view'
		];
		$edit = [
			'query' => 'action=edit',
			'msg' => 'edit'
		];
		return [
			'should be able to disable page tools' => [
				'[Foo](https://foo.bar/index.php/Foo)',
				[],
				'Foo',
			],
			'should urlencode special characters' => [
				'[Foo&bar](https://foo.bar/index.php/Foo%26bar)',
				[],
				'Foo&bar',
			],
			'should render the view tool' => [
				'[Foo](https://foo.bar/index.php/Foo)',
				[ $view ],
				'Foo',
			],
			'should render a tool with query' => [
				'[Foo](https://foo.bar/index.php/Foo) ([Edit](https://foo.bar/index.php?title=Foo&action=edit))',
				[ $edit ],
				'Foo',
			],
			'"view" should be omitted in the block style' => [
				'[Foo](https://foo.bar/index.php/Foo)',
				[
					[
						'target' => 'view',
						'text' => 'view'
					]
				],
				'Foo',
			],
		];
	}

	/**
	 * @dataProvider providerDiscordPageText
	 * @covers \MediaWiki\Extension\DiscordRCFeed\DiscordLinker::makePageTextWithTools
	 */
	public function testMakePageTextWithTools(
		string $expected,
		array $pageTools,
		string $titleText
	) {
		$this->setMwGlobals( [
			'wgServer' => 'https://foo.bar',
			'wgArticlePath' => '/index.php/$1',
			'wgScript' => '/index.php'
		] );
		$linkRenderer = new DiscordLinker( null, $pageTools );
		$page = $this->getExistingTestPage( $titleText );
		$title = $page->getTitle();
		$actual = $linkRenderer->makePageTextWithTools( $title );
		$this->assertSame( $expected, $actual );
	}

	public static function providerPageTools(): array {
		$tools = [];
		foreach ( [ 'edit', 'delete', 'history', 'diff' ] as $tool ) {
			$tools[$tool] = [
				'query' => "action=$tool",
				'text' => $tool,
			];
		}
		$view = [
			'target' => 'view',
			'text' => 'view'
		];
		return [
			'all tools should be shown in the structured style' => [
				'[edit](https://foo.bar/index.php?title=Foo&action=edit)' . PHP_EOL
				. '[delete](https://foo.bar/index.php?title=Foo&action=delete)' . PHP_EOL
				. '[history](https://foo.bar/index.php?title=Foo&action=history)',
				[ $tools['edit'], $tools['delete'], $tools['history'] ],
				'Foo',
				[ PHP_EOL, true ],
			],
			'all tools should be shown in the embed style' => [
				'[edit](https://foo.bar/index.php?title=Foo&action=edit) | '
				. '[delete](https://foo.bar/index.php?title=Foo&action=delete) | '
				. '[history](https://foo.bar/index.php?title=Foo&action=history)',
				[ $tools['edit'], $tools['delete'], $tools['history'] ],
				'Foo',
				[ null, false ],
			],
			'should include the self link if the includeSelf is true' => [
				'[view](https://foo.bar/index.php?title=Foo&oldid=22)',
				[ $view ],
				'Foo',
				[ null, true ],
			],
			'should not include the self link if the includeSelf is false' => [
				'',
				[ $view ],
				'Foo',
				[ null, false ],
			],
			'should not include tools other then the view for a special page' => [
				'[view](https://foo.bar/index.php/Special:Version)',
				[ $view, $tools['edit'], $tools['diff'] ],
				'Special:Version',
				[ null, true ],
			],
		];
	}

	/**
	 * @dataProvider providerPageTools
	 * @covers \MediaWiki\Extension\DiscordRCFeed\DiscordLinker::makePageTools
	 */
	public function testMakePageTools(
		string $expected,
		array $pageTools,
		string $titleText,
		array $params
	) {
		$this->setMwGlobals( [
			'wgServer' => 'https://foo.bar',
			'wgArticlePath' => '/index.php/$1',
			'wgScript' => '/index.php'
		] );
		$title = Title::newFromText( $titleText );

		$revMock = $this->createMock( RevisionRecord::class );
		$revMock->method( 'getId' )
			->willReturn( 22 );
		$mock = $this->createMock( RevisionStore::class );
		$mock->method( 'getRevisionByTitle' )
			->willReturnCallback( static function () use ( $title, $revMock )
			{
				return $title->isSpecialPage() ? null : $revMock;
			}
		);
		$this->setService( 'RevisionStore', $mock );

		$linkRenderer = new DiscordLinker( null, $pageTools );

		$this->assertSame(
			$expected,
			$linkRenderer->makePageTools( $title, ...$params )
		);
	}

	public static function diffProvider() {
		return [
			'should render diff if the title is not root' => [
				'[Dummy page](https://foo.bar/index.php/Dummy_page) '
				. '([diff](https://foo.bar/index.php?title=Dummy_page&oldid=11&diff=prev))',
				false,
			],
			'should not render diff if the title is root' => [
				'[Dummy page](https://foo.bar/index.php/Dummy_page)',
				true,
			],
		];
	}

	/**
	 * @dataProvider diffProvider
	 * @covers \MediaWiki\Extension\DiscordRCFeed\DiscordLinker::makePageTextWithTools
	 */
	public function testDiffPageTool( $expected, $titleIsRoot ) {
		$this->setMwGlobals( [
			'wgServer' => 'https://foo.bar',
			'wgArticlePath' => '/index.php/$1',
			'wgScript' => '/index.php'
		] );
		$diffTool = [
			'target' => 'diff',
			'text' => 'diff',
		];
		$linkRenderer = new DiscordLinker( null, [ $diffTool ] );

		$title = $this->mockTitle( $titleIsRoot );
		$actual = $linkRenderer->makePageTextWithTools( $title );
		$this->assertSame( $expected, $actual );
	}

	public function mockTitle( $isRoot ): Title {
		$title = $this->getExistingTestPage( 'Dummy page' )->getTitle();

		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getParentId' )
			->willReturn( $isRoot ? null : 10 );
		$revision->method( 'getId' )
			->willReturn( 11 );

		$store = $this->createMock( RevisionStore::class );
		$store->method( 'getRevisionByTitle' )
			->willReturn( $revision );
		$this->setService( 'RevisionStore', $store );

		return $title;
	}
}

<?php

namespace MediaWiki\Extension\DiscordRCFeed\Tests\Integration;

use ExtensionRegistry;
use MediaWiki\Extension\DiscordRCFeed\HtmlToDiscordConverter;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;
use Title;
use Wikimedia\TestingAccessWrapper;

/**
 * @group DiscordRCFeed
 * @group Database
 *
 * @covers \MediaWiki\Extension\DiscordRCFeed\HtmlToDiscordConverter
 */
class HtmlToDiscordConverterTest extends MediaWikiIntegrationTestCase {

	/** @var HtmlToDiscordConverter */
	private $converter;

	/** @var TestingAccessWrapper */
	private $wrapper;

	protected function setUp(): void {
		parent::setUp();
		$this->converter = new HtmlToDiscordConverter();
		$this->wrapper = TestingAccessWrapper::newFromObject( $this->converter );
	}

	public static function providerHtml(): array {
		$data = [
			'should convert auto comment' => [
				'[→‎Section](http://f.oo/index.php/Main_Page#Section)',
				'<span dir="auto"><span class="autocomment">'
				. '<a href="/index.php/Main_Page#Section" title="Main Page">→‎Section</a></span></span>'
			]
		];
		if ( ExtensionRegistry::getInstance()->isLoaded( 'Flow' ) ) {
			$data += [
				'should convert user link' => [
					'[Admin](http://f.oo/index.php/User:Admin) '
					. '[commented](http://f.oo/index.php?title='
					. 'Topic:Wh925tqnitcssmp8&topic_showPostId=wh925tqnixav0qng#flow-post-wh925tqnixav0qng) '
					. 'on "Lorem" (Ipsum) ([Lorem](http://f.oo/index.php/Topic:Wh925tqnitcssmp8) on '
					. '[Talk:Main Page](http://f.oo/w/Talk:%EB%8C%80%EB%AC%B8))',

					'<a href="/index.php?title=User:Admin&amp;action=edit&amp;redlink=1" class="new '
					. 'mw-userlink fw-link" title="User:Admin (page does not exist)"><bdi>Admin</bdi>'
					. '</a> <span class="mw-usertoollinks">(<a href="/index.php?title=User_talk:Admin&amp;'
					. 'action=edit&amp;redlink=1" class="new mw-usertoollinks-talk fw-link" title="User '
					. 'talk:Admin (page does not exist)">talk</a> | <a href="/w/Special:Contributions/Admin"'
					. ' class="mw-usertoollinks-contribs fw-link" title="Special:Contributions/Admin">contribs</a>'
					. ' | <a href="/w/Special:Block/Admin" class="mw-usertoollinks-block fw-link" '
					. 'title="Special:Block/Admin">block</a>)</span> <a target="_blank" rel="nofollow '
					. 'noreferrer noopener" class="external text" href="http://f.oo/index.php?'
					. 'title=Topic:Wh925tqnitcssmp8&amp;topic_showPostId=wh925tqnixav0qng#flow-post-wh925tqnixav0qng">'
					. 'commented</a> on "Lorem" (<em>Ipsum</em>) (<a href="/w/Topic:Wh925tqnitcssmp8" title="Lorem">'
					. 'Lorem</a> on <a href="/w/Talk:%EB%8C%80%EB%AC%B8" class="mw-title fw-link" '
					. 'title="Talk:Main Page">'
					. 'Talk:Main Page</a>)',
					'convert()'
				],
			];
		}
		return $data;
	}

	/**
	 * @dataProvider providerHtml
	 * @covers \MediaWiki\Extension\DiscordRCFeed\HtmlToDiscordConverter::convert
	 */
	public function testConvert( $expected, $html ) {
		$this->overrideConfigValues( [
			MainConfigNames::Server => 'http://f.oo',
			MainConfigNames::ArticlePath => '/index.php/$1',
			MainConfigNames::Script => '/index.php',
		] );
		$this->assertSame(
			$expected,
			$this->converter->convert( $html )
		);
	}

	public static function providerUserName(): array {
		return [
			[
				'[Admin](https://foo.bar/index.php/User:Admin)',
				'<a href="/index.php?title=User:Admin&amp;action=view&amp;redlink=1" '
				. 'class="new mw-userlink fw-link" title="User:Admin (page does not exist)"><bdi>Admin</bdi></a>',
				'convertUserName()',
			],
		];
	}

	/**
	 * @dataProvider providerUserName
	 * @covers \MediaWiki\Extension\DiscordRCFeed\HtmlToDiscordConverter::convertUserName
	 */
	public function testConvertUserName( $expected, $params, $message = '' ) {
		$this->overrideConfigValues( [
			MainConfigNames::Server => 'https://foo.bar',
			MainConfigNames::ArticlePath => '/index.php/$1',
			MainConfigNames::Script => '/index.php',
		] );
		$this->assertSame(
			$expected,
			$this->wrapper->convertUserName( $params ),
			$message
		);
	}

	public static function providerTitleHtml(): array {
		return [
			[
				'[Main Page](https://foo.bar/index.php/Main_Page)',
				'<a href="/index.php/Main_Page" title="Main Page">Main Page</a>',
				'/index.php/$1',
				'should replace a title link when the article path is "/index.php/$1"',
			],
			[
				'[Main Page](https://foo.bar/wiki/Main_Page)',
				'<a href="/wiki/Main_Page" title="Main Page">Main Page</a>',
				'/wiki/$1',
				'should replace a title link when the article path is "/wiki/$1"',
			],
			[
				'[Main Page](https://foo.bar/w/Main_Page)',
				'<a href="/w/Main_Page" title="Main Page">Main Page</a>',
				'/w/$1',
				'should replace a title link when the article path is "/w/$1"',
			],
			[
				'[Main Page](https://foo.bar/index.php/Main_Page) and [Main Page](https://foo.bar/index.php/Main_Page)',
				'<a href="/w/Main_Page" title="Main Page">Main Page</a>'
				. ' and <a href="/w/Main_Page" title="Main Page">Main Page</a>',
				'/index.php/$1',
				'should replace multiple title links',
			],
			[
				'[User:Admin/Test123](https://foo.bar/wiki/User:Admin/Test123)',
				'<a href="/wiki/User:Admin/Test123" title="User:Admin/Test123">User:Admin/Test123</a>',
				'/wiki/$1',
				'should convert a title link with subpage when $wgArticlePath is "/wiki/$1"',
			],
			[
				'[→‎Section](https://foo.bar/index.php/Main_Page#Section)',
				'<a href="/index.php/Main_Page#Section" title="Main Page">→‎Section</a>',
				'/index.php/$1',
				'should convert auto comment',
			],
		];
	}

	/**
	 * @dataProvider providerTitleHtml
	 * @covers \MediaWiki\Extension\DiscordRCFeed\HtmlToDiscordConverter::convertTitleLinks
	 */
	public function testConvertTitleLinks( $expected, $params, $articlePath, $message = '' ) {
		$this->overrideConfigValues( [
			MainConfigNames::Server => 'https://foo.bar',
			MainConfigNames::ArticlePath => $articlePath,
			MainConfigNames::Script => '/index.php',
		] );
		$this->assertSame(
			$expected,
			$this->wrapper->convertTitleLinks( $params ),
			$message
		);
	}

	public static function providerLinks(): array {
		return [
			[
				'[commented](https://foo.bar/Talk:Wh8294kyt52ohquw)',
				'<a rel="nofollow noreferrer noopener" class="text" '
				. 'href="https://foo.bar/Talk:Wh8294kyt52ohquw">'
				. 'commented</a>',
				'should replace link to wiki page'
			],
			[
				'[Label](https://foo.bar/index.php?query=1&query2=1#fragment)',
				'<a target="_blank" rel="nofollow noreferrer noopener" class="external text" '
				. 'href="https://foo.bar/index.php?query=1&query2=1#fragment">Label</a>',
				'should replace link contains query and fragment'
			]
		];
	}

	/**
	 * @dataProvider providerLinks
	 * @covers \MediaWiki\Extension\DiscordRCFeed\HtmlToDiscordConverter::convertLinks
	 */
	public function testConvertLinks( $expected, $params, $message = '' ) {
		$this->overrideConfigValues( [
			MainConfigNames::Server => 'https://foo.bar',
			MainConfigNames::ArticlePath => '/index.php/$1',
			MainConfigNames::Script => '/index.php',
		] );
		$this->assertSame(
			$expected,
			$this->wrapper->convertLinks( $params ),
			$message
		);
	}

	public static function providerTitle(): array {
		$data = [
			'should include links for title' => [
				true,
				'Foo',
			],
		];

		if ( ExtensionRegistry::getInstance()->isLoaded( 'Flow' ) ) {
			$data += [
				'should not include links for discussion board page' => [
					false,
					'Talk:Foo',
				],
				'should not include links for topic page' => [
					false,
					'Topic:Wh92jykmy8scu7l8',
				],
			];
		}
		return $data;
	}

	/**
	 * @dataProvider providerTitle
	 * @covers \MediaWiki\Extension\DiscordRCFeed\HtmlToDiscordConverter::shouldIncludeTitleLinks
	 */
	public function testShouldIncludeTitleLinks( $expected, $titleText ) {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'Flow' ) ) {
			$this->mergeMwGlobalArrayValue(
				'wgNamespaceContentModels',
				[
					NS_TALK => CONTENT_MODEL_FLOW_BOARD,
				]
			);
		}
		$this->assertSame(
			$expected,
			$this->wrapper->shouldIncludeTitleLinks( Title::newFromText( $titleText ) )
		);
	}
}

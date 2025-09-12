<?php
namespace MediaWiki\Extension\DiscordRCFeed;

use MediaWiki\RecentChanges\RecentChange;

final class Constants {
	public const DEFAULT_RC_FEED_PARAMS = [
		'formatter' => DiscordRCFeedFormatter::class,
		'class' => DiscordRCFeedEngine::class,
		'style' => DiscordRCFeedFormatter::STYLE_STRUCTURE,
		'user_tools' => [
			[
				'target' => 'user_page',
				'msg' => 'nstab-user'
			],
			[
				'target' => 'talk',
				'msg' => 'talkpagelinktext'
			],
			[
				'target' => 'special',
				'special' => 'Contributions',
				'msg' => 'contribslink'
			],
		],
		'page_tools' => [
			[
				'target' => 'diff',
				'msg' => 'diff'
			],
			[
				'target' => 'view',
				'msg' => 'view'
			],
			[
				'query' => 'action=history',
				'msg' => 'hist'
			],
		],
		'omit_log_types' => [
			'patrol',
		],
		'omit_talk' => false,
		'only_talk' => false,
	];

	public const RC_FEED_MUST_BE_ARRAY_PARAMS = [
		'omit_namespaces',
		'only_namespaces',
		'omit_sources',
		'only_sources',
		'omit_log_types',
		'only_log_types',
		'omit_log_actions',
		'only_log_actions',
		'omit_usernames',
		'only_usernames',
		'omit_pages',
		'only_pages',
		'omit_content_models',
		'only_content_models',
	];

	private const COLOR_GRAY = 0xb3b4bc;
	private const COLOR_GREEN = 0x2daf32;
	private const COLOR_BLUE = 0x36a1e8;
	private const COLOR_RED = 0xe83535;
	private const COLOR_MAGENTA = 0xd635e8;
	private const COLOR_CYAN = 0x00ffff;
	private const COLOR_DARK_BLUE = 0x354fe8;

	public const COLOR_DEFAULT = self::COLOR_GRAY;

	public const COLOR_MAP_ACTION = [
		RecentChange::SRC_EDIT => self::COLOR_GREEN,
		RecentChange::SRC_NEW  => self::COLOR_BLUE,
		RecentChange::SRC_LOG  => self::COLOR_GREEN,
	];

	public const COLOR_MAP_LOG = [
		'delete'   => self::COLOR_RED,
		'move'     => self::COLOR_MAGENTA,
		'protect'  => self::COLOR_DARK_BLUE,
		'upload'   => self::COLOR_BLUE,
		'newusers' => self::COLOR_BLUE,
		'rights'   => self::COLOR_GREEN,
		'block'    => self::COLOR_RED,
		'import'   => self::COLOR_GREEN,
	];

	public const COLOR_ACTION_FLOW = self::COLOR_CYAN;
}

<?php
namespace MediaWiki\Extension\DiscordRCFeed;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use SpecialPage;
use User;

class DiscordLinker {
	/** @var array */
	private $userTools;

	/** @var array */
	private $pageTools;

	/**
	 * @param array $userTools
	 * @param array $pageTools
	 */
	public function __construct( $userTools = [], $pageTools = [] ) {
		$this->userTools = $userTools;
		$this->pageTools = $pageTools;
	}

	/**
	 * @param array $tools given tool definitions via $wgRCFeeds.
	 * @param callable $makeLink
	 * @param string|null $sep A separator string used as a glue when imploding the tools. If not given,
	 *     the pipe character(|) is used
	 * @return string
	 */
	private static function makeTools( array $tools, callable $makeLink, $sep = null ) {
		$links = [];
		foreach ( $tools as $tool ) {
			$link = $makeLink( $tool );
			if ( !$link ) {
				continue;
			}
			$label = isset( $tool['msg'] ) ? Util::msgText( $tool['msg'] ) : $tool['text'];
			$links[] = self::makeLink( $link, $label );
		}
		return implode( $sep ?: Util::msgText( 'pipe-separator' ), $links );
	}

	/**
	 * @param User $user
	 * @param string|null $sep A separator string used as a glue when imploding the tools. If not given,
	 *     the pipe character(|) is used
	 * @param bool $includeSelf Whether to include a link to the given user's user page.
	 * @return string
	 */
	public function makeUserTools( User $user, $sep = null, $includeSelf = false ): string {
		return self::makeTools(
			$this->userTools,
			static function ( $tool ) use ( $user, $includeSelf ) {
				if ( $tool['target'] == 'user_page' ) {
					if ( $includeSelf ) {
						return $user->getUserPage()->getFullURL( '', false, PROTO_CURRENT );
					} else {
						return null;
					}
				}
				if ( $tool['target'] == 'talk' ) {
					return $user->getTalkPage()->getFullURL( '', false, PROTO_CURRENT );
				}
				return SpecialPage::getTitleFor( $tool['special'], $user->getName() )
					->getFullURL( '', false, PROTO_CURRENT );
			},
			$sep
		);
	}

	/**
	 * @param Title $title
	 * @param string|null $sep A separator string used as a glue when imploding the tools. If not given,
	 *     the pipe character(|) is used
	 * @param bool $includeSelf Whether to include a link to the given title page.
	 * @return string
	 */
	public function makePageTools( Title $title, $sep = null, $includeSelf = false ): string {
		return self::makeTools(
			$this->pageTools,
			static function ( $tool ) use ( $title, $includeSelf ) {
				if ( isset( $tool['target'] ) ) {
					$store = MediaWikiServices::getInstance()->getRevisionStore();
					$revision = $store->getRevisionByTitle( $title );
					$revisionId = $revision ? $revision->getId() : null;
					if ( $tool['target'] == 'view' ) {
						if ( $includeSelf ) {
							return $title->getFullURL( $revisionId ? "oldid=$revisionId" : '', false, PROTO_CURRENT );
						} else {
							return null;
						}
					}
					if ( $tool['target'] == 'diff' ) {
						if ( $title->isSpecialPage() ) {
							return;
						}
						if ( !$revision ) {
							return null;
						}
						$parentId = $revision->getParentId();
						if ( !$parentId ) {
							// New page, skips diff
							return null;
						}
						return $title->getFullURL( "oldid=$revisionId&diff=prev", false, PROTO_CURRENT );
					}
				}
				if ( $title->isSpecialPage() ) {
					return null;
				}
				return $title->getFullURL( $tool['query'] ?? '', false, PROTO_CURRENT );
			},
			$sep
		);
	}

	/**
	 * Gets nice HTML text for user containing the link to user page and also links to user site,
	 * groups editing, talk and contribs pages if configured.
	 * @param User $user
	 * @return string
	 */
	public function makeUserTextWithTools( User $user ): string {
		$rt = self::makeLink( $user->getUserPage()->getFullURL( '', false, PROTO_CURRENT ), $user->getName() );
		if ( $this->userTools ) {
			$tools = $this->makeUserTools( $user );
			if ( !$tools ) {
				return $rt;
			}
			$tools = Util::msgText( 'parentheses', $tools );
			$rt .= " $tools";
		}
		return $rt;
	}

	/**
	 * Gets nice HTML text for article containing the link to article page
	 * and also into edit, delete and article history pages.
	 * @param Title $title
	 * @return string
	 */
	public function makePageTextWithTools( Title $title ): string {
		$rt = self::makeLink( $title->getFullURL( '', false, PROTO_CURRENT ), $title->getFullText() );
		if ( $this->pageTools ) {
			$tools = $this->makePageTools( $title );
			if ( !$tools ) {
				return $rt;
			}
			$tools = Util::msgText( 'parentheses', $tools );
			$rt .= " $tools";
		}
		return $rt;
	}

	/**
	 * @param string $target
	 * @param string $text
	 * @return string
	 */
	public static function makeLink( string $target, string $text ): string {
		if ( !$target ) {
			return $text;
		}
		$target = self::parseUrl( $target );
		return "[$text]($target)";
	}

	/**
	 * Replaces some special characters on urls. This has to be done as Discord webhook api does not
	 * accept urlencoded text.
	 * @param string $url
	 * @return string
	 */
	private static function parseUrl( string $url ): string {
		return str_replace(
			[
				' ',
				'(',
				')',
			],
			[
				'%20',
				'%28',
				'%29',
			],
			$url
		);
	}
}

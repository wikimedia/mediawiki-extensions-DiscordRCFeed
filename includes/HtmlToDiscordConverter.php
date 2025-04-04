<?php
namespace MediaWiki\Extension\DiscordRCFeed;

use ExtensionRegistry;
use MediaWiki\Title\Title;
use Sanitizer;
use User;

class HtmlToDiscordConverter {
	// phpcs:disable Generic.Files.LineLength.TooLong
	/** @var string */
	private const REGEX_FOR_USER_LINK = '#<a[^>]+class=[\'"][^\'"]*mw-userlink[^\'"]*[\'"][^>]*><bdi>([^<+]+)</bdi></a>#';

	/** @var string */
	private const REGEX_FOR_USER_TOOLS = '#<span[^>]+class=[\'"][^\'"]*mw-usertoollinks[^\'"]*[\'"][^>]*>.+</span>#';

	/** @var string */
	private const REGEX_FOR_TITLE_LINK = '#<a[^>]+href=[\'"][^\'"=]*(?:wiki/|w/|index.php/)([^\'"]+)[\'"][^>]*title=[^>]*>([^<+]+)</a>#';

	/** @var string */
	private const REGEX_FOR_GENERAL_LINK = '#<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>([^<+]+)</a>#';
	// phpcs:enable Generic.Files.LineLength.TooLong

	/** @var DiscordLinker */
	private $linker;

	/**
	 * @param DiscordLinker|null $linker
	 */
	public function __construct( $linker = null ) {
		$this->linker = $linker ?? new DiscordLinker();
	}

	/**
	 * @param string $text
	 * @param bool $omitTools
	 * @return string
	 */
	public function convert( string $text, bool $omitTools = false ): string {
		$text = $this->removeUserTools( $text );
		$text = $this->convertUserName( $text );
		$text = $this->convertTitleLinks( $text, $omitTools );
		$text = $this->convertLinks( $text );

		$text = Sanitizer::stripAllTags( $text );
		return $text;
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function convertUserName( string $text ): string {
		if ( preg_match_all( self::REGEX_FOR_USER_LINK, $text, $matches ) ) {
			foreach ( $matches[0] as $i => $fullMatch ) {
				$username = $matches[1][$i];
				$user = User::newFromName( $username );
				if ( !$user ) {
					continue;
				}
				$replace = $this->linker->makeUserTextWithTools( $user );
				$text = str_replace( $fullMatch, $replace, $text );
			}
		}
		return $text;
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function removeUserTools( string $text ): string {
		return preg_replace( self::REGEX_FOR_USER_TOOLS, '', $text );
	}

	/**
	 * @param string $text
	 * @param bool $omitTools
	 * @return string
	 */
	private function convertTitleLinks( string $text, bool $omitTools = false ): string {
		if ( preg_match_all( self::REGEX_FOR_TITLE_LINK, $text, $matches ) ) {
			foreach ( $matches[0] as $i => $fullMatch ) {
				$url = $matches[1][$i];
				$label = $matches[2][$i];

				$title = Title::newFromUrl( $url );
				if ( !$title ) {
					continue;
				}
				if ( !$omitTools && self::shouldIncludeTitleLinks( $title ) ) {
					$replace = $this->linker->makePageTextWithTools( $title );
				} else {
					$replace = DiscordLinker::makeLink( $title->getFullURL( '', false, PROTO_CURRENT ), $label );
				}
				$text = str_replace( $fullMatch, $replace, $text );
			}
		}
		return $text;
	}

	/**
	 * @param Title $title
	 * @return bool
	 */
	private static function shouldIncludeTitleLinks( $title ): bool {
		if ( $title->hasFragment() ) {
			return false;
		}
		if ( ExtensionRegistry::getInstance()->isLoaded( 'Flow' ) ) {
			if ( $title->getContentModel() == CONTENT_MODEL_FLOW_BOARD ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function convertLinks( string $text ): string {
		if ( preg_match_all( self::REGEX_FOR_GENERAL_LINK, $text, $matches ) ) {
			foreach ( $matches[0] as $i => $fullMatch ) {
				$url = $matches[1][$i];
				$url = Util::urlIsLocal( $url ) ? wfExpandUrl( $url ) : '';
				$label = $matches[2][$i];

				$link = DiscordLinker::makeLink( $url, $label );
				$text = str_replace( $fullMatch, $link, $text );
			}
		}
		return $text;
	}
}

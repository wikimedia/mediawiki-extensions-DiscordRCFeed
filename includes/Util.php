<?php

namespace MediaWiki\Extension\DiscordRCFeed;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Message;
use MessageSpecifier;
use Psr\Log\LoggerInterface;
use RecentChange;
use RequestContext;
use User;

final class Util {
	/** @var LoggerInterface */
	private static $logger = null;

	/**
	 * @return LoggerInterface
	 */
	public static function getLogger(): LoggerInterface {
		if ( !self::$logger ) {
			self::$logger = LoggerFactory::getInstance( 'DiscordRCFeed' );
		}
		return self::$logger;
	}

	/**
	 * @param string|string[]|MessageSpecifier $key Message key, or array of keys, or a MessageSpecifier
	 * @param mixed ...$params Normal message parameters
	 * @return string
	 */
	public static function msgText( $key, ...$params ): string {
		$message = new Message( $key );

		if ( $params ) {
			$message->params( ...$params );
		}
		return $message->inContentLanguage()->text();
	}

	/**
	 * @param string $url
	 * @return bool
	 */
	public static function urlIsLocal( string $url ): bool {
		$services = MediaWikiServices::getInstance();
		$urlUtils = $services->getUrlUtils();
		$server = $urlUtils->parse( $services->getMainConfig()->get( MainConfigNames::Server ) );
		$url = $urlUtils->parse( $url );
		$bitNames = [
			'scheme',
			'host',
			'port',
		];
		foreach ( $bitNames as $name ) {
			// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
			if ( isset( $url[$name] ) && $server[$name] !== $url[$name] ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * get a context which has the content language to prevent the message shown in an arbitrary language the editor
	 * uses.
	 * https://github.com/femiwiki/DiscordRCFeed/issues/6
	 * @return RequestContext
	 */
	public static function getContentLanguageContext(): RequestContext {
		$context = RequestContext::getMain();
		$context->setLanguage( MediaWikiServices::getInstance()->getContentLanguage() );
		return $context;
	}

	/**
	 * @param RecentChange $rc
	 * @return Title|null
	 */
	public static function getTitleFromRC( RecentChange $rc ) {
		return Title::castFromPageReference( $rc->getPage() );
	}

	/**
	 * @param RecentChange $rc
	 * @return User
	 */
	public static function getPerformerFromRC( RecentChange $rc ) {
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		return $userFactory->newFromUserIdentity( $rc->getPerformerIdentity() );
	}
}

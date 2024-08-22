<?php

namespace MediaWiki\Extension\DiscordRCFeed;

use FatalError;
use LogFormatter;
use LogFormatterFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use Message;
use RCFeedFormatter;
use RecentChange;
use Title;
use User;

class DiscordRCFeedFormatter implements RCFeedFormatter {

	public const STYLE_INLINE = 'inline';
	public const STYLE_EMBED = 'embed';
	public const STYLE_STRUCTURE = 'structure';

	/** @var DiscordLinker */
	private $linker;

	/** @var HtmlToDiscordConverter */
	private $converter;

	/** @var FlowDiscordFormatter */
	private $flowFormatter;

	/** @var array|null */
	private $feed;

	/** @var User|null */
	private $performer;

	/** @var Title|null */
	private $title;

	/** @var string */
	private $style;

	/**
	 * @param array|null $feed
	 * @param User|null $performer
	 * @param Title|null $title
	 * @throws FatalError
	 */
	public function __construct( $feed = null, $performer = null, $title = null ) {
		if ( $feed === null || $performer === null || $title === null ) {
			return;
		}
		if ( !defined( 'MW_PHPUNIT_TEST' ) ) {
			throw new FatalError( 'Data must be passed to getLine() method only.' );
		}
		$this->initialize( $feed, $performer, $title );
	}

	/**
	 * @param array|null $feed
	 * @param User|null $performer
	 * @param Title|null $title
	 */
	public function initialize( $feed = null, $performer = null, $title = null ) {
		$feed += [
			'user_tools' => null,
			'page_tools' => null,
		];

		$this->feed = $feed;
		$this->performer = $performer;
		$this->title = $title;
		$this->linker = new DiscordLinker( $feed['user_tools'], $feed['page_tools'] );
		$this->converter = new HtmlToDiscordConverter( $this->linker );
		if ( isset( $feed['style'] ) ) {
			$this->style = $feed['style'];
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getLine( array $feed, RecentChange $rc, $actionComment ) {
		$this->initialize( $feed, Util::getPerformerFromRC( $rc ), Util::getTitleFromRC( $rc ) );
		$attribs = $rc->getAttributes();
		$rcType = $attribs['rc_type'];

		if ( self::isFlowLoaded() && $rcType == RC_FLOW ) {
			$plaintext = $this->style == self::STYLE_STRUCTURE;
			$this->flowFormatter = new FlowDiscordFormatter( $rc, $this->converter, $plaintext );
		}

		$desc = $this->getDescription( $rc );

		$services = MediaWikiServices::getInstance();

		if ( in_array( $rcType, [ RC_EDIT, RC_NEW ] ) ) {
			$color = Constants::COLOR_MAP_ACTION[$rcType] ?? Constants::COLOR_DEFAULT;
			$store = $services->getCommentStore();
			$comment = $store->getComment( 'rc_comment', $attribs )->text;
		} elseif ( $rcType == RC_LOG ) {
			$color = Constants::COLOR_MAP_LOG[$attribs['rc_log_type']] ?? Constants::COLOR_MAP_ACTION[RC_LOG];
			$comment = $attribs['rc_comment'];
		} elseif ( self::isFlowLoaded() && $rcType == RC_FLOW ) {
			$color = Constants::COLOR_ACTION_FLOW;
			$comment = $this->flowFormatter->getI18nProperty( 'summary' );
		} else {
			return null;
		}
		if ( $comment ) {
			$formatter = $services->getCommentFormatter();
			$comment = $formatter->format( $comment, $this->title, false, null );
			$comment = $this->converter->convert( $comment, true );
		}

		return $this->makePostData( $attribs, $color, $desc, $comment );
	}

	/**
	 * Returns description that includes an emoji and a text message.
	 * @param RecentChange $rc
	 * @return string
	 */
	private function getDescription( RecentChange $rc ): string {
		$attribs = $rc->getAttributes();
		$rcType = $attribs['rc_type'];
		if ( in_array( $rcType, [ RC_EDIT, RC_NEW ] ) ) {
			$desc = $this->getEditDescription( $attribs );
		} elseif ( $rcType == RC_LOG ) {
			$desc = $this->getLogDescription( $attribs );
		} elseif ( self::isFlowLoaded() && $rcType == RC_FLOW ) {
			$desc = $this->getFlowDescription( $rc );
		} else {
			return '';
		}

		return "$desc";
	}

	/**
	 * @param array $attribs
	 * @return string
	 */
	private function getEditDescription( array $attribs ): string {
		$performer = $this->performer;
		$title = $this->title;

		$flag = '';
		if ( $attribs['rc_minor'] ) {
			$flag .= '-minor';
		}
		if ( $attribs['rc_bot'] ) {
			$flag .= '-bot';
		}

		if ( $attribs['rc_type'] == RC_NEW ) {
			$emoji = Util::msgText( 'discordrcfeed-emoji-log-create-create' );
			$desc = 'logentry-create-create';
		} else {
			// i18n messages:
			//  discordrcfeed-emoji-edit
			//  discordrcfeed-emoji-edit-minor
			//  discordrcfeed-emoji-edit-bot
			//  discordrcfeed-emoji-edit-minor-bot
			$emoji = Util::msgText( 'discordrcfeed-emoji-edit' . $flag );
			// i18n messages:
			//  discordrcfeed-emoji-edit
			//  discordrcfeed-emoji-edit-minor
			//  discordrcfeed-emoji-edit-bot
			//  discordrcfeed-emoji-edit-minor-bot
			$desc = 'discordrcfeed-line-edit' . $flag;
		}
		$desc = new Message( $desc );

		if ( $this->style == self::STYLE_STRUCTURE ) {
			// description for structure style is plaintext and does not contain links.
			$params = [
				// $1: username
				$performer->getName(),
				// $2: username for GENDER
				$performer->getName(),
				// $3
				Util::msgText( 'quotation-marks', $title->getFullText() ),
			];
		} else {
			$params = [
				// $1: username
				$this->linker->makeUserTextWithTools( $performer ),
				// $2: username for GENDER
				$performer->getName(),
				// $3
				$this->linker->makePageTextWithTools( $title ),
			];
		}
		$desc = $desc->params( ...$params )->inContentLanguage()->text();
		return "$emoji $desc";
	}

	/**
	 * @param array $attribs
	 * @return string
	 */
	private function getLogDescription( array $attribs ): string {
		$logType = $attribs['rc_log_type'];
		$logAction = $attribs['rc_log_action'];

		$emoji = self::getEmojiForKeys( 'discordrcfeed-emoji-log', $logType, $logAction );

		// Prevent an unknown user to be displayed because of bug: https://phabricator.wikimedia.org/T286979
		if ( !isset( $attribs['rc_actor'] ) ) {
			$actorStore = MediaWikiServices::getInstance()->getActorStore();
			$userFactory = MediaWikiServices::getInstance()->getUserFactory();
			$db = wfGetDB( DB_REPLICA );
			if ( isset( $attribs['rc_user'] ) && $attribs['rc_user'] !== 0 ) {
				$user = $userFactory->newFromId( $attribs['rc_user'] )->getUser();
				$attribs['rc_actor'] = $actorStore->findActorId( $user, $db );
			} elseif ( isset( $attribs['rc_user_text'] ) ) {
				$attribs['rc_actor'] = $actorStore->findActorIdByName( $attribs['rc_user_text'], $db );
			}
		}
		if ( class_exists( LogFormatterFactory::class ) ) {
			// MW 1.42+
			$formatter = MediaWikiServices::getInstance()->getLogFormatterFactory()->newFromRow( $attribs );
		} else {
			$formatter = LogFormatter::newFromRow( $attribs );
		}
		$formatter->setContext( Util::getContentLanguageContext() );
		if ( $this->style == self::STYLE_STRUCTURE ) {
			// description for structure style is plaintext and does not contain links.
			$desc = $formatter->getPlainActionText();
			// Replace square brackets that even the plain action text includes.
			$desc = str_replace( [ '[[', ']]' ], '"', $desc );
		} else {
			$desc = $formatter->getActionText();
			$desc = $this->converter->convert( $desc );
		}
		return "$emoji $desc";
	}

	/**
	 * @param RecentChange $rc
	 * @return string
	 */
	private function getFlowDescription( RecentChange $rc ): string {
		$attribs = $rc->getAttributes();
		$action = unserialize( $attribs['rc_params'] )['flow-workflow-change']['action'];
		$emoji = self::getEmojiForKeys( 'discordrcfeed-emoji-flow', $action );
		$desc = $this->flowFormatter->getDiscordDescription();

		return "$emoji $desc";
	}

	/**
	 * @param string $prefix
	 * @param string $mainKey
	 * @param string $subKey
	 * @param string $fallback
	 * @return string
	 */
	private static function getEmojiForKeys( string $prefix, string $mainKey,
		string $subKey = '', string $fallback = ''
	): string {
		$keys = array_filter( [
			$subKey ? "$prefix-$mainKey-$subKey" : '',
			$mainKey ? "$prefix-$mainKey" : '',
			$fallback ?: "$prefix",
		] );
		foreach ( $keys as $key ) {
			$msg = new Message( $key );
			if ( $msg->exists() ) {
				return $msg->inContentLanguage()->text();
			}
		}
		return '';
	}

	/**
	 * @param mixed $attribs
	 * @return string|null
	 */
	private function getSizeDiff( $attribs ) {
		if ( !isset( $attribs['rc_new_len'] ) || $attribs['rc_new_len'] === null ) {
			return null;
		}
		if ( $attribs['rc_type'] == RC_NEW ||
			!isset( $attribs['rc_old_len'] ) || $attribs['rc_old_len'] === null ) {
			// old length is missing. just return the formatted new length
			$msg = ( new Message( 'nbytes' ) )->numParams( $attribs['rc_new_len'] );
			$len = $msg->inContentLanguage()->text();
			if ( $this->style != self::STYLE_STRUCTURE ) {
				$len = Util::msgText( 'parentheses', $len );
			}
			return $len;
		}

		// diff is calculable
		$szdiff = $attribs['rc_new_len'] - $attribs['rc_old_len'];
		if ( $this->style == self::STYLE_STRUCTURE ) {
			$newLen = $attribs['rc_new_len'];
			$newLen = ( new Message( 'nbytes' ) )->numParams( $newLen );
			$newLen = $newLen->inContentLanguage()->text();
			$szdiff = ( $szdiff > 0 ? '+' : '' ) . $szdiff;
			$szdiff = Util::msgText( 'parentheses', $szdiff );
			return "$newLen $szdiff";
		} else {
			$msg = ( new Message( 'nbytes' ) )->numParams( $szdiff );
			$msg = $msg->inContentLanguage()->text();
			$msg = ( $szdiff > 0 ? '+' : '' ) . $msg;
			$msg = Util::msgText( 'parentheses', $msg );
			return $msg;
		}
	}

	/**
	 * https://discord.com/developers/docs/resources/webhook#execute-webhook
	 * @param array $attribs
	 * @param int $color
	 * @param string|null $desc
	 * @param string|null $comment
	 * @return string
	 */
	private function makePostData(
		array $attribs,
		int $color = Constants::COLOR_DEFAULT,
		$desc = null,
		$comment = null
	): string {
		$performer = $this->performer;
		$title = $this->title;
		$style = $this->style;

		$szdiff = $this->getSizeDiff( $attribs );
		if ( $style != self::STYLE_STRUCTURE && $comment ) {
			$msg = new Message( 'parentheses' );
			$msg->plaintextParams( $comment );
			$comment = $msg->inContentLanguage()->text();
		}

		$fullString = implode( ' ', array_filter( [
			$desc,
			$comment,
			$szdiff,
		] ) );

		$post = [
			'username' => MediaWikiServices::getInstance()->getMainConfig()
				->get( MainConfigNames::Sitename ),
		];
		switch ( $style ) {
			case self::STYLE_EMBED:
				$post['embeds'] = [
					[
						'color' => $color,
						'description' => $fullString,
					],
				];
				break;
			case self::STYLE_INLINE:
				$post['content'] = $fullString;
				break;
			case self::STYLE_STRUCTURE:
				$szdiff = $this->getSizeDiff( $attribs );
				$post['embeds'] = [
					[
						'color' => $color,
						'description' => $desc,
						'fields' => [],
					],
				];
				if ( $performer ) {
					$tools = $this->linker->makeUserTools( $performer, null, true );
					if ( $tools ) {
						$post['embeds'][0]['fields'][] = [
							'name' => $performer->getName(),
							'value' => $tools,
							'inline' => true,
						];
					}
				}
				if ( !self::isFlowLoaded() || $attribs['rc_type'] !== RC_FLOW ) {
					if ( $title ) {
						$tools = $this->linker->makePageTools( $title, null, true );
						if ( $tools ) {
							$post['embeds'][0]['fields'][] = [
								'name' => $title->getFullText(),
								'value' => $tools,
								'inline' => true,
							];
						}
					}
				} else {
					$fields = $this->getFlowPageToolFields();
					$post['embeds'][0]['fields'] = array_merge(
						$post['embeds'][0]['fields'], $fields );
				}
				if ( $szdiff ) {
					$post['embeds'][0]['fields'][] = [
						'name' => Util::msgText( 'listfiles_size' ),
						'value' => $szdiff,
						'inline' => true,
					];
				}
				if ( $comment ) {
					$post['embeds'][0]['fields'][] = [
						'name' => Util::msgText( 'summary' ),
						'value' => $comment,
					];
				}
				break;
		}
		if ( isset( $this->feed['request_replace'] ) ) {
			$post = array_replace_recursive( $post, $this->feed['request_replace'] );
		}
		return json_encode( $post );
	}

	/**
	 * @return array
	 */
	private function getFlowPageToolFields() {
		$title = $this->title;
		$flowFormatter = $this->flowFormatter;
		$linker = $this->linker;

		$fields = [];

		// Add topic field
		$tools = [];
		$topicText = $flowFormatter->getI18nProperty( 'post-of-summary' )
			?: $title->getFullText();

		// Add view tool
		$viewLink = $flowFormatter->getI18nProperty( 'post-url' );
		$viewLink = $viewLink ?: $flowFormatter->getI18nProperty( 'workflow-url' );
		if ( $viewLink ) {
			$tools[] = DiscordLinker::makeLink( $viewLink, Util::msgText( 'view' ) );
		}

		$sep = Util::msgText( 'pipe-separator' );
		$toolsText = implode( $sep, $tools );
		if ( $toolsText ) {
			$toolsText .= $sep;
		}
		$toolsText .= $linker->makePageTools( $title );
		if ( $toolsText ) {
			$fields[] = [
				'name' => $topicText,
				'value' => $toolsText,
				'inline' => true,
			];
		}

		return $fields;
	}

	/**
	 * @return bool
	 */
	private static function isFlowLoaded(): bool {
		return \ExtensionRegistry::getInstance()->isLoaded( 'Flow' );
	}
}

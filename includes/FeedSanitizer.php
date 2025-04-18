<?php

namespace MediaWiki\Extension\DiscordRCFeed;

class FeedSanitizer implements \MediaWiki\Hook\MediaWikiServicesHook {

	/**
	 * Modifies RC Feeds with keys start with 'discord'.
	 * @inheritDoc
	 */
	public function onMediaWikiServices( $services ) {
		global $wgRCFeeds;
		if ( !$wgRCFeeds ) {
			return;
		}

		foreach ( $wgRCFeeds as $feedKey => $feed ) {
			if ( strpos( $feedKey, 'discord' ) !== 0 ) {
				continue;
			}
			if ( !isset( $wgRCFeeds[$feedKey]['url'] ) ) {
				// Reads 'uri' which is a key for other RCFeedFormatters, like
				// JSONRCFeedFormatter and IRCColourfulRCFeedFormatter as the fallback of 'url'.
				// DiscordRCFeedEngine should read only 'url', but this makes it less confusing for the end user.
				if ( isset( $wgRCFeeds[$feedKey]['uri'] ) ) {
					$wgRCFeeds[$feedKey]['url'] = $wgRCFeeds[$feedKey]['uri'];
				} else {
					continue;
				}
			}

			// Don't send RC_CATEGORIZE events (same as T127360)
			$mergeParams = [
				'omit_types' => [ RC_CATEGORIZE ],
			];

			self::initializeParameters(
				$wgRCFeeds[$feedKey],
				Constants::DEFAULT_RC_FEED_PARAMS,
				$mergeParams,
				Constants::RC_FEED_MUST_BE_ARRAY_PARAMS
			);
		}
	}

	/**
	 * $feed has some requirements, default parameters and type matching. This function makes sure
	 * the requirements match for the given &$feed.
	 * @param array &$feed
	 * @param array|null $defaultParameters
	 * @param array|null $mergeParameters
	 * @param array $mustBeArray
	 */
	public static function initializeParameters(
		&$feed,
		$defaultParameters = null,
		$mergeParameters = null,
		$mustBeArray = []
	) {
		// Makes sure always being array.
		foreach ( $mustBeArray as $param ) {
			if ( isset( $feed[$param] ) ) {
				if ( !is_array( $feed[$param] ) ) {
					$feed[$param] = [ $feed[$param] ];
				}
			} else {
				$feed[$param] = [];
			}
		}

		if ( $defaultParameters ) {
			// array_replace_recursive but to the second level.
			foreach ( $defaultParameters as $k => $v ) {
				if ( !isset( $feed[$k] ) || !$feed[$k] ) {
					$feed[$k] = $v;
				}
			}
		}
		if ( $mergeParameters ) {
			$feed = array_merge_recursive( $feed, $mergeParameters );
		}
	}
}

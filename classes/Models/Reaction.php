<?php
/**
 * Like dislike model
 *
 * @package solidie
 */

namespace Solidie\Models;

use Solidie\Helpers\_Array;

class Reaction {

	private $type;
	private $content_id;

	/**
	 * Register reaction type for further actions
	 *
	 * @param string $type
	 */
	public function __construct( string $type, int $content_id ) {
		$this->type       = $type;
		$this->content_id = $content_id;
	}

	/**
	 * Get instance for wishlist
	 *
	 * @param int $content_id
	 *
	 * @return self
	 */
	public static function wishlist( $content_id ) {
		return new self( 'wishlist', $content_id );
	}

	/**
	 * Get instance for rating
	 *
	 * @param int $content_id
	 *
	 * @return self
	 */
	public static function rating( $content_id ) {
		return new self( 'rating', $content_id );
	}

	/**
	 * Get instance for like dislike
	 *
	 * @param int $content_id
	 *
	 * @return self
	 */
	public static function like( $content_id ) {
		return new self( 'like',  $content_id);
	}

	/**
	 * Get count of reaction regardless of type
	 *
	 * @param integer $value
	 * @param string $operator
	 * @return int
	 */
	private function getCount( $value = 0, $operator = '>' ) {

		global $wpdb;

		$value_clause = $wpdb->prepare( " AND value{$operator}%d", $value );

		return ( int ) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 
					COUNT(reaction_id)
				FROM 
					{$wpdb->solidie_reactions} 
				WHERE 
					content_id=%d 
					AND reaction_type=%s
					{$value_clause}",
				$this->content_id,
				$this->type
			)
		);
	}
	
	/**
	 * Get average value. Do not call for non rating reactions.
	 *
	 * @return int
	 */
	public function getAverage() {
		
		global $wpdb;

		$average = ( float ) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(value) FROM {$wpdb->solidie_reactions} WHERE content_id=%d AND reaction_type=%s",
				$this->content_id,
				$this->type
			)
		);

		return number_format( $average, 1 );
	}

	/**
	 * Apply reaction to a content.
	 *
	 * @param int $value Minus one means to delete reaction of the user
	 * @return void
	 */
	public function applyReaction( $value, $user_id ) {

		global $wpdb;

		// Delete the reaction  the value is negative
		if ( $value < 0 ) {
			$wpdb->delete(
				$wpdb->solidie_reactions,
				array(
					'content_id'    => $this->content_id,
					'user_id'       => $user_id,
					'reaction_type' => $this->type,
				)
			);

			return;
		}

		// Get existing reaction
		$reaction = $this->getReaction( $user_id );

		$payload = array(
			'content_id'    => $this->content_id,
			'user_id'       => $user_id,
			'value'         => $value,
			'reaction_type' => $this->type,
			'reaction_date' => gmdate( 'Y-m-d H:i:s' ),
		);

		if ( empty( $reaction ) ) {
			// If empty, create new entry
			$wpdb->insert(
				$wpdb->solidie_reactions,
				$payload
			);

		} else {
			// If not empty update the existing reaction value
			$wpdb->update(
				$wpdb->solidie_reactions,
				$payload,
				array( 'reaction_id' => $reaction['reaction_id'] )
			);
		}
	}

	/**
	 * Get reaction to a content by user id
	 *
	 * @param int $user_id
	 * @return array
	 */
	public function getReaction( $user_id, $key = null ) {

		global $wpdb;

		$reaction = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->solidie_reactions} WHERE content_id=%d AND user_id=%d AND reaction_type=%s",
				$this->content_id,
				$user_id,
				$this->type
			),
			ARRAY_A
		);

		$row = _Array::castRecursive( _Array::getArray( $reaction ) );

		return $key ? ( $row[ $key ] ?? null ) : $row;
	}

	/**
	 * Get reactions stats to a content by user ID
	 *
	 * @param int $content_id
	 * @param integer $user_id
	 * @return array
	 */
	public static function getStats( $content_id, int $user_id ) {

		$stats             = array();
		$content_type      = Field::contents()->getField( array( 'content_id' => $content_id ), 'content_type' );
		$feedback_settings = AdminSetting::getFeedbackSettings( $content_type );

		// Get Rating info
		if ( $feedback_settings['rating'] ) {
			$reaction = self::rating( $content_id );
			$stats['rating'] = array(
				'rating_count' => $reaction->getCount(),
				'average'      => $reaction->getAverage(),
				'my_reaction'  => $reaction->getReaction( $user_id, 'value' )
			);

		} else if ( $feedback_settings['like'] ) {

			// Get like dislike info
			$reaction = self::like( $content_id );

			$stats['like'] = array(
				'like_count'    => $reaction->getCount(),
				'dislike_count' => $feedback_settings['dislike'] ? $reaction->getCount( 0, '=' ) : null,
				'my_reaction'   => $reaction->getReaction( $user_id, 'value' )
			);
		}

		$stats['commenting'] = $feedback_settings['comment'];

		return $stats;
	}
}

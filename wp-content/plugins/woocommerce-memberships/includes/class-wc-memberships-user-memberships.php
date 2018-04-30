<?php
/**
 * WooCommerce Memberships
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Memberships to newer
 * versions in the future. If you wish to customize WooCommerce Memberships for your
 * needs please refer to https://docs.woocommerce.com/document/woocommerce-memberships/ for more information.
 *
 * @package   WC-Memberships/Classes
 * @author    SkyVerge
 * @copyright Copyright (c) 2014-2018, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * User Memberships handler.
 *
 * This class handles general user memberships related functionality.
 *
 * @since 1.0.0
 */
class WC_Memberships_User_Memberships {


	/** @var array cached user memberships by user ID and query args */
	private $user_memberships = array();

	/** @var array cached user membership \WP_Post objects for given plans indexed by user ID and plan ID */
	private $user_membership_post_by_plan = array();

	/** @var array cached user membership \WP_Post objects indexed by post ID */
	private $user_membership_post_by_id = array();

	/** @var array memoization helper is user member check */
	private $is_user_member = array();

	/** @var string helper pending note for a user membership */
	private $membership_status_transition_note;


	/**
	 * Memberships handler constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		require_once( wc_memberships()->get_plugin_path() . '/includes/class-wc-memberships-user-membership.php' );

		// post lifecycle and statuses events handling
		add_filter( 'wp_insert_post_data',               array( $this, 'adjust_user_membership_post_data' ) );
		add_action( 'transition_post_status',            array( $this, 'transition_post_status' ), 10, 3 );
		add_action( 'save_post',                         array( $this, 'save_user_membership' ), 10, 3 );
		add_action( 'delete_user',                       array( $this, 'delete_user_memberships' ) );
		add_action( 'delete_post',                       array( $this, 'delete_related_data' ) );
		add_action( 'trashed_post',                      array( $this, 'handle_order_trashed' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'handle_order_refunded' ) );

		// prevent User Membership notes (ie. comments on user memberships posts) from showing where not supposed to
		add_filter( 'comments_clauses',   array( $this, 'exclude_membership_notes_from_queries' ) );
		add_action( 'comment_feed_join',  array( $this, 'exclude_membership_notes_from_feed_join' ) );
		add_action( 'comment_feed_where', array( $this, 'exclude_membership_notes_from_feed_where' ) );
		add_filter( 'wp_count_comments',  array( $this, 'exclude_membership_notes_from_comments_count' ), 999, 2 );

		// expiration events handling
		add_action( 'wc_memberships_user_membership_expiry',           array( $this, 'trigger_expiration_events' ), 10, 1 );
		add_action( 'wc_memberships_user_membership_expiring_soon',    array( $this, 'trigger_expiration_events' ), 10, 1 );
		add_action( 'wc_memberships_user_membership_renewal_reminder', array( $this, 'trigger_expiration_events' ), 10, 1 );
	}


	/**
	 * Creates a new user membership or renews an existing one.
	 *
	 * Returns a new user membership object on success which can then be used to add additional data.
	 * Throws an exception on errors.
	 *
	 * @since 1.9.0
	 *
	 * @param array $args array of arguments
	 * @param string $action either 'create' or 'renew' -- when in doubt, use 'create'
	 * @return \WC_Memberships_User_Membership
	 * @throws \SV_WC_Plugin_Exception throws an exception if a user membership could not be created or the related plan is invalid or not found
	 */
	public function create_user_membership( $args = array(), $action = 'create' ) {

		$args = wp_parse_args( $args, array(
			'user_membership_id' => 0,
			'plan_id'            => 0,
			'user_id'            => 0,
			'product_id'         => 0,
			'order_id'           => 0,
		) );

		$new_membership_data = array(
			'post_parent'    => (int) $args['plan_id'],
			'post_author'    => (int) $args['user_id'],
			'post_type'      => 'wc_user_membership',
			'post_status'    => 'wcm-active',
			'comment_status' => 'open',
		);

		$updating = false;

		if ( (int) $args['user_membership_id'] > 0 ) {
			$updating                  = true;
			$new_membership_data['ID'] = (int) $args['user_membership_id'];
		}

		/**
		 * Filter new membership data, used when a product purchase grants access.
		 *
		 * @since 1.0.0
		 * @param array $data
		 * @param array $args array of User Membership arguments {
		 *     @type int $user_id the user id the membership is assigned to
		 *     @type int $product_id the product id that grants access (optional)
		 *     @type int $order_id the order id that contains the product that granted access (optional)
		 * }
		 */
		$new_post_data = apply_filters( 'wc_memberships_new_membership_data', $new_membership_data, array(
			'user_id'    => (int) $args['user_id'],
			'product_id' => (int) $args['product_id'],
			'order_id'   => (int) $args['order_id'],
		) );

		// bail out if a plan cannot be found before setting a new user membership
		if ( ! wc_memberships_get_membership_plan( $args['plan_id'] ) ) {
			/* translators: Placeholder: %s - membership plan ID */
			throw new SV_WC_Plugin_Exception( sprintf( __( 'Cannot create User Membership: Membership Plan with ID %d does not exist', 'woocommerce-memberships' ), (int) $args['plan_id'] ) );
		}

		if ( $updating ) {

			// do not modify the post status yet on renewals
			unset( $new_post_data['post_status'] );

			$user_membership_id = wp_update_post( $new_post_data, true );

		} else {

			$user_membership_id = wp_insert_post( $new_post_data, true );
		}

		// bail out on error
		if ( 0 === $user_membership_id || is_wp_error( $user_membership_id ) ) {
			/* translators: Placeholder: %s - error message(s) */
			throw new SV_WC_Plugin_Exception( sprintf( __( 'Cannot create User Membership: %s.', 'woocommerce-memberships' ), implode( ', ', $user_membership_id->get_error_messages() ) ) );
		}

		// get the user membership object to set properties on
		$user_membership = $this->get_user_membership( $user_membership_id );

		// this shouldn't happen, yet ensure $user_membership isn't null
		if ( ! $user_membership instanceof WC_Memberships_User_Membership ) {
			/* translators: Placeholder: %s - membership plan ID */
			throw new SV_WC_Plugin_Exception( sprintf( __( 'Cannot create User Membership "%s".', 'woocommerce-memberships' ), $user_membership_id ) );
		}

		// save/update product id that granted access
		if ( (int) $args['product_id'] > 0 ) {
			$user_membership->set_product_id( $args['product_id'] );
		}

		// save/update the order id that contained the access granting product
		if ( (int) $args['order_id'] > 0 ) {
			$user_membership->set_order_id( $args['order_id'] );
		}

		$this->prune_object_caches();

		// get the user membership object again, since the product and the order just set might influence the object filtering (e.g. Subscriptions)
		/** @see \WC_Memberships_Integration_Subscriptions_Abstract::get_user_membership() */
		$user_membership = $this->get_user_membership( $user_membership_id );
		// get the membership plan object to get some properties from
		$membership_plan = wc_memberships_get_membership_plan( (int) $args['plan_id'], $user_membership );

		// Save or update the membership start date,
		// but only if the membership is not active yet (ie. is not being renewed);
		// also do a sanity check for delayed memberships:
		if ( 'renew' !== $action ) {

			$start_date = $membership_plan->is_access_length_type( 'fixed' ) ? $membership_plan->get_access_start_date() : current_time( 'mysql', true );

			$user_membership->set_start_date( $start_date );

		} elseif ( 'delayed' !== $user_membership->get_status() && $user_membership->get_start_date( 'timestamp' ) > strtotime( 'tomorrow', current_time( 'timestamp', true ) ) ) {

			$user_membership->update_status( 'delayed' );
		}

		// Calculate membership end date based on membership length,
		// early renewals add to the existing membership length,
		// normal cases calculate membership length from "now" (UTC).
		$now        = current_time( 'timestamp', true );
		$is_expired = $user_membership->is_expired();

		if ( 'renew' === $action && ! $is_expired ) {
			$end = $user_membership->get_end_date( 'timestamp' );
			$now = ! empty( $end ) ? $end : $now;
		}

		// obtain the relative end date based on the membership plan
		$end_date = $membership_plan->get_expiration_date( $now, $args );

		// save/update the membership end date
		$user_membership->set_end_date( $end_date );

		// finally, re-activate successfully renewed memberships after setting new dates
		if ( 'renew' === $action && $user_membership->is_in_active_period() ) {

			if ( $is_expired ) {

				$user_membership->update_status( 'active' );

			} elseif ( $user_membership->has_status( 'cancelled' ) ) {

				/**
				 * Toggles whether to renew a cancelled user membership.
				 *
				 * @param bool $renew_cancelled_membership whether to renew a cancelled membership, default true
				 * @param \WC_Memberships_User_Membership $user_membership the cancelled user membership being renewed
				 * @param array $args arguments used in the renewal process
				 */
				$renew_cancelled_membership = (bool) apply_filters( 'wc_memberships_renew_cancelled_membership', true, $user_membership, $args );

				if ( true === $renew_cancelled_membership ) {

					$user_membership->update_status( 'active' );
				}
			}
		}

		/**
		 * Fires after a user has been granted membership access.
		 *
		 * This action hook is similar to `wc_memberships_user_membership_saved`
		 * but doesn't fire when memberships are manually created from admin.
		 * @see \WC_Memberships_User_Memberships::save_user_membership()
		 *
		 * @since 1.3.0
		 *
		 * @param \WC_Memberships_Membership_Plan $membership_plan the plan that user was granted access to
		 * @param array $args array of User Membership arguments {
		 *     @type int $user_id the user ID the membership is assigned to
		 *     @type int $user_membership_id the user membership ID being saved
		 *     @type bool $is_update whether this is a post update or a newly created membership
		 * }
		 */
		do_action( 'wc_memberships_user_membership_created', $membership_plan, array(
			'user_id'            => $args['user_id'],
			'user_membership_id' => $user_membership->get_id(),
			'is_update'          => $updating,
		) );

		$this->prune_object_caches();

		return $user_membership;
	}


	/**
	 * Returns all user memberships.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id optional, defaults to current user
	 * @param array $args optional arguments
	 * @return \WC_Memberships_User_Membership[] array of user memberships
	 */
	public function get_user_memberships( $user_id = null, $args = array() ) {

		$args = wp_parse_args( $args, array(
			'status' => 'any',
		) );

		// add the wcm- prefix for the status if it's not "any"
		foreach ( (array) $args['status'] as $index => $status ) {

			if ( 'any' !== $status ) {
				$args['status'][ $index ] = 'wcm-' . $status;
			}
		}

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$user_memberships = array();

		if ( is_numeric( $user_id ) && $user_id > 0 ) {

			$posts_args = array(
				'author'      => $user_id,
				'post_type'   => 'wc_user_membership',
				'post_status' => $args['status'],
				'nopaging'    => true,
			);

			$cache_key = http_build_query( $posts_args );

			if ( ! isset( $this->user_memberships[ $user_id ][ $cache_key ] ) ) {

				$posts = get_posts( $posts_args );

				foreach ( $posts as $post ) {
					if ( $user_membership = $this->get_user_membership( $post ) ) {
						$user_memberships[] = $user_membership;
					}
				}

				$this->user_memberships[ $user_id ][ $cache_key ] = $user_memberships;
			}

			$user_memberships = $this->user_memberships[ $user_id ][ $cache_key ];
		}

		return $user_memberships;
	}


	/**
	 * Returns a User Membership.
	 *
	 * Supports getting user membership by membership id, post object or a combination of the user id and membership plan id/slug/post object.
	 * If no $id is provided, defaults to getting the membership for the current user.
	 *
	 * @since 1.0.0
	 *
	 * @param int|\WC_Memberships_User_Membership $id optional: post object or post ID of the User Membership, or user ID
	 * @param int|string|\WC_Memberships_Membership_Plan optional: Membership Plan slug, post object or related post ID
	 * @return \WC_Memberships_User_Membership|null
	 */
	public function get_user_membership( $id = null, $plan = null ) {

		// if a plan is provided, try to find the User Membership using user ID + plan ID
		if ( $plan ) {

			if ( $id instanceof WP_User ) {
				$user_id = $id->ID;
			} else {
				$user_id = ! empty( $id ) ? (int) $id : get_current_user_id();
			}

			$membership_plan = wc_memberships_get_membership_plan( $plan );

			// bail out if no user ID or membership plan
			if ( ! $membership_plan || ! $user_id || 0 === $user_id ) {
				return null;
			}

			$plan_id = (int) $membership_plan->get_id();

			if ( ! isset( $this->user_membership_post_by_plan[ $user_id ][ $plan_id ] ) ) {

				$user_memberships = get_posts( array(
					'author'      => $user_id,
					'post_type'   => 'wc_user_membership',
					'post_parent' => $plan_id,
					'post_status' => 'any',
				) );

				$this->user_membership_post_by_plan[ $user_id ][ $plan_id ] = ! empty( $user_memberships ) ? $user_memberships[0] : null;
			}

			$post = $this->user_membership_post_by_plan[ $user_id ][ $plan_id ];

		// otherwise, try to get user membership directly
		} else {

			$user_membership_id   = 0;
			$user_membership_post = $post = null;

			if ( is_numeric( $id ) ) {
				$user_membership_id   = (int) $id;
			} elseif ( $id instanceof WP_Post ) {
				$user_membership_post = $id;
				$user_membership_id   = (int) $id->ID;
			} elseif ( $id instanceof WC_Memberships_User_Membership ) {
				$user_membership_post = $id->post;
				$user_membership_id   = $id->get_id();
			} elseif ( false === $id && $GLOBALS['post'] instanceof WP_Post ) {
				$user_membership_post = $GLOBALS['post'];
				$user_membership_id   = (int) $GLOBALS['post']->ID;
			}

			if ( $user_membership_id > 0 ) {

				if ( ! isset( $this->user_membership_post_by_id[ $user_membership_id ] ) ) {
					$this->user_membership_post_by_id[ $user_membership_id ] = $user_membership_post instanceof WP_Post ? $user_membership_post : get_post( $user_membership_id );
				}

				$post = $this->user_membership_post_by_id[ $user_membership_id ];
			}
		}

		// if no acceptable post is found, bail out
		if ( ! $post || 'wc_user_membership' !== get_post_type( $post ) ) {
			return null;
		}

		$user_membership = new WC_Memberships_User_Membership( $post );

		/**
		 * Filter the user membership.
		 *
		 * This is an important filter as it's also used internally when the membership is connected to Subscriptions.
		 *
		 * @since 1.7.0
		 *
		 * @param \WC_Memberships_User_Membership $user_membership the user membership
		 * @param \WP_Post $post the user membership post object
		 * @param int $id the user membership ID or the user ID if $plan is not null
		 * @param null|\WC_Memberships_Membership_Plan $plan optional, the membership plan object
		 */
		return apply_filters( 'wc_memberships_user_membership', $user_membership, $post, $id, $plan );
	}


	/**
	 * Prunes object caches for stored memberships.
	 *
	 * @since 1.9.8
	 *
	 * @param null|\WC_Memberships_User_Membership $user_membership
	 */
	private function prune_object_caches( $user_membership = null ) {

		if ( $user_membership instanceof WC_Memberships_User_Membership ) {

			unset(
				$this->user_memberships[ $user_membership->get_user_id() ],
				$this->user_membership_post_by_plan[ $user_membership->get_user_id() ],
				$this->user_membership_post_by_id[ $user_membership->get_id() ]
			);

		}  else {

			unset( $this->user_memberships, $this->user_membership_post_by_plan, $this->user_membership_post_by_id );

			$this->user_memberships = $this->user_membership_post_by_plan = $this->user_membership_post_by_id = array();
		}
	}


	/**
	 * Returns a user membership from an order ID.
	 *
	 * @since 1.0.1
	 *
	 * @param int|\WC_Order $order order object or ID
	 * @return null|\WC_Memberships_User_Membership[]
	 */
	public function get_user_membership_by_order_id( $order ) {

		if ( is_numeric( $order ) ) {
			$order_id = (int) $order;
		} elseif ( $order instanceof WC_Order || $order instanceof WC_Order_Refund ) {
			$order_id = (int) SV_WC_Order_Compatibility::get_prop( $order, 'id' );
		} else {
			return null;
		}

		$user_memberships_query = new WP_Query( array(
			'fields'      => 'ids',
			'nopaging'    => true,
			'post_type'   => 'wc_user_membership',
			'post_status' => 'any',
			'meta_key'    => '_order_id',
			'meta_value'  => $order_id,
		) );

		if ( empty( $user_memberships_query ) ) {
			return null;
		}

		$user_memberships_posts = $user_memberships_query->get_posts();
		$user_memberships       = array();

		foreach ( $user_memberships_posts as $post_id ) {

			if ( $user_membership = $this->get_user_membership( $post_id ) ) {

				$user_memberships[] = $user_membership;
			}
		}

		return $user_memberships;
	}


	/**
	 * Checks if user is a member of one particular or any membership plan.
	 *
	 * @since 1.0.0
	 *
	 * @param int|\WP_User $user_id optional, defaults to current user
	 * @param int|string|\WC_Memberships_Membership_Plan $membership_plan optional: membership plan ID, object or slug - leave empty to check if the user is a member of any plan
	 * @param bool|string $check_if_active optional additional check to see if the member has currently active access (pass param as true or 'active') or delayed access (use 'delayed')
	 * @param bool $cache whether to use cached results (default true)
	 * @return bool
	 */
	public function is_user_member( $user_id = null, $membership_plan = null, $check_if_active = false, $cache = true ) {

		$is_member = false;

		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		} elseif ( isset( $user_id->ID ) ) {
			$user_id = $user_id->ID;
		}

		// sanity check (invalid user or not logged in)
		if ( ! is_numeric( $user_id ) || 0 === $user_id ) {
			return $is_member;
		} else {
			$user_id = (int) $user_id;
		}

		$plan_id = null;

		if ( is_numeric( $membership_plan ) ) {
			$plan_id = $membership_plan;
		} elseif ( $membership_plan instanceof WC_Memberships_Membership_Plan ) {
			$plan_id = $membership_plan->get_id();
		}

		$member_status_cache_key = null;

		// set status check cache key
		if ( true === $check_if_active ) {
			$member_status_cache_key = 'is_active';
		} elseif ( ! $check_if_active ) {
			$member_status_cache_key = 'is_member';
		} elseif ( is_string( $check_if_active ) ) {
			$member_status_cache_key = "is_{$check_if_active}";
		}

		// use memoization to fetch a value faster, if user member status is cached
		if (    false !== $cache
		     && $member_status_cache_key
		     && is_numeric( $plan_id )
		     && isset( $this->is_user_member[ $user_id ][ $plan_id ][ $member_status_cache_key ] ) ) {

			$is_member = $this->is_user_member[ $user_id ][ $plan_id ][ $member_status_cache_key ];

		} else {

			// note 'true' is for legacy purposes here (check for active)
			$must_be_active_member = in_array( $check_if_active, array( 'active', 'delayed', true ), true );

			if ( null === $membership_plan ) {

				// check if the user is a member of at least one plan
				$plans = wc_memberships_get_membership_plans();

				if ( ! empty( $plans ) ) {

					foreach ( $plans as $plan ) {

						if ( $user_membership = $this->get_user_membership( $user_id, $plan ) ) {

							// if not checking for active memberships
							// $must_be_active_member === false, then $is_member === true
							$is_member = ! $must_be_active_member;

							if ( true === $must_be_active_member ) {

								if ( $is_member = ( $user_membership->is_active() && $user_membership->is_in_active_period() ) ) {

									// return true if we are checking for currently active
									break;

								} elseif ( 'delayed' === $check_if_active && ( $is_member = $user_membership->is_delayed() ) ) {

									// return true if we are checking if start is delayed
									break;
								}

							} else {

								// just returns true if user is a member
								break;
							}
						}
					}
				}

			} else {

				// check if the user is a member of a specific plan
				$user_membership = $this->get_user_membership( $user_id, $membership_plan );
				$is_member       = (bool) $user_membership;

				if ( $is_member && $must_be_active_member ) {

					$is_member = $user_membership->is_active() && $user_membership->is_in_active_period();

					// maybe we want to check if this is a delayed membership due to future access date
					if ( 'delayed' === $check_if_active ) {

						$is_member = $user_membership->is_delayed();
					}
				}
			}

			$this->is_user_member[ $user_id ][ $plan_id ][ $member_status_cache_key ] = $is_member;
		}

		return $is_member;
	}


	/**
	 * Checks if user is a member with active access of one particular or any membership plan
	 *
	 * @since 1.0.0
	 *
	 * @param int|\WP_User $user_id optional, defaults to current user
	 * @param int|string $membership_plan optional: membership plan ID or slug - leave empty to check if the user is a member of any plan
	 * @param bool $cache whether to use cache results (default true)
	 * @return bool
	 */
	public function is_user_active_member( $user_id = null, $membership_plan = null, $cache = true ) {
		return $this->is_user_member( $user_id, $membership_plan, 'active', $cache );
	}


	/**
	 * Checks if user is an active member of one particular or any membership plan but is delayed.
	 *
	 * This is when a member has not gained access yet because the start date of the plan is in the future.
	 *
	 * @since 1.7.0
	 *
	 * @param int|\WP_User $user_id optional, defaults to current user
	 * @param int|string $membership_plan optional: membership plan ID or slug - leave empty to check if the user is a member of any plan
	 * @param bool $cache whether to use cache results (default true)
	 * @return bool
	 */
	public function is_user_delayed_member( $user_id = null, $membership_plan = null, $cache = true ) {
		return $this->is_user_member( $user_id, $membership_plan, 'delayed', $cache );
	}


	/**
	 * Checks if user is either a member with active or delayed access of one particular or any membership plan.
	 *
	 * Note: this isn't the equivalent of doing `! wc_memberships_is_user_active_member()`
	 * @see \WC_Memberships_User_Memberships::is_user_active_member()
	 * @see \WC_Memberships_User_Memberships::is_user_delayed_member()
	 *
	 * @since 1.7.0
	 *
	 * @param int|\WP_User $user_id optional, defaults to current user
	 * @param int|string $membership_plan optional: membership plan ID or slug - leave empty to check if the user is a member of any plan
	 * @param bool $cache whether to use cache results (default true)
	 * @return bool
	 */
	public function is_user_active_or_delayed_member( $user_id = null, $membership_plan = null, $cache = true ) {
		return    $this->is_user_active_member( $user_id, $membership_plan, $cache )
		       || $this->is_user_delayed_member( $user_id, $membership_plan, $cache );
	}


	/**
	 * Returns the total count of user membership notes.
	 *
	 * @since 1.10.1
	 *
	 * @param null|int|\WC_Memberships_User_Membership optional user membership to return count for, otherwise returns a global count
	 * @return int
	 */
	public function get_user_membership_notes_count( $user_membership = null ) {
		global $wpdb;

		$user_membership = is_numeric( $user_membership ) ? $this->get_user_membership( $user_membership ) : $user_membership;

		if ( $user_membership instanceof WC_Memberships_User_Membership ) {

			$count = count( $user_membership->get_notes() );

		} else {

			$count = $wpdb->get_var( "
				SELECT COUNT(comment_ID)
    			FROM $wpdb->comments
    			WHERE comment_post_ID in (
      				SELECT ID
      				FROM $wpdb->posts
      				WHERE post_type = 'wc_user_membership'
      			)
  			" );
		}

		return is_numeric( $count ) ? max( 0, (int) $count ) : 0;
	}


	/**
	 * Returns the earliest date a user has been a member of any plan.
	 *
	 * @since 1.7.0
	 *
	 * @param \WP_User|int $user_id the user ID or object
	 * @param string $format the format the date should be, either 'timestamp', 'mysql' or php date format (default timestamp)
	 * @return int|string|null timestamp, date string or null if error or user isn't a member
	 */
	public function get_user_member_since_date( $user_id, $format = 'timestamp' ) {

		if ( $user_id instanceof WP_User ) {
			$user_id = $user_id->ID;
		}

		if ( ! is_numeric( $user_id ) ) {
			return null;
		}

		$user_memberships = $this->get_user_memberships( $user_id );
		$member_since     = null;

		foreach ( $user_memberships as $user_membership ) {

			if ( ! $member_since || $member_since > $user_membership->get_start_date( 'timestamp' ) ) {

				$member_since = $user_membership->get_start_date( 'timestamp' );
			}
		}

		return $member_since ? wc_memberships_format_date( $member_since, $format ) : null;
	}


	/**
	 * Returns the earliest local date a user has been a member of any plan.
	 *
	 * @since 1.7.0
	 *
	 * @param int $user_id the user ID
	 * @param string $format the format the date should be, either 'timestamp', 'mysql' or php date format (default timestamp)
	 * @return int|string|null timestamp, date string or null if error or user isn't a member
	 */
	public function get_user_member_since_local_date( $user_id, $format = 'timestamp' ) {

		// get the date timestamp
		$date = $this->get_user_member_since_date( $user_id, $format );

		// adjust the date to the site's local timezone
		return ! empty( $date ) ? wc_memberships_adjust_date_by_timezone( $date, $format ) : null;
	}


	/**
	 * Returns all user membership statuses.
	 *
	 * @since 1.0.0
	 *
	 * @return array associative array of statuses and their arguments
	 */
	public function get_user_membership_statuses() {

		$statuses = array(

			'wcm-active'        => array(
				'label'       => _x( 'Active', 'Membership Status', 'woocommerce-memberships' ),
				/* translators: Active Membership(s) */
				'label_count' => _n_noop( 'Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>', 'woocommerce-memberships' ),
			),

			'wcm-delayed'       => array(
				'label'       => _x( 'Delayed', 'Membership status', 'woocommerce-memberships' ),
				/* translators: Delayed Membership(s) */
				'label_count' => _n_noop( 'Delayed <span class="count">(%s)</span>', 'Delayed <span class="count">(%s)</span>', 'woocommerce-memberships' ),
			),

			'wcm-complimentary' => array(
				'label'       => _x( 'Complimentary', 'Membership Status', 'woocommerce-memberships' ),
				/* translators: Complimentary Membership(s) */
				'label_count' => _n_noop( 'Complimentary <span class="count">(%s)</span>', 'Complimentary <span class="count">(%s)</span>', 'woocommerce-memberships' ),
			),

			'wcm-pending'       => array(
				'label'       => _x( 'Pending Cancellation', 'Membership Status', 'woocommerce-memberships' ),
				/* translators: Membership(s) Pending Cancellation */
				'label_count' => _n_noop( 'Pending Cancellation <span class="count">(%s)</span>', 'Pending Cancellation <span class="count">(%s)</span>', 'woocommerce-memberships' ),
			),

			'wcm-paused'        => array(
				'label'       => _x( 'Paused', 'Membership Status', 'woocommerce-memberships' ),
				/* translators: Paused Membership(s) */
				'label_count' => _n_noop( 'Paused <span class="count">(%s)</span>', 'Paused <span class="count">(%s)</span>', 'woocommerce-memberships' ),
			),

			'wcm-expired'       => array(
				'label'       => _x( 'Expired', 'Membership Status', 'woocommerce-memberships' ),
				/* translators: Expired Membership(s) */
				'label_count' => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'woocommerce-memberships' ),
			),

			'wcm-cancelled'     => array(
				'label'       => _x( 'Cancelled', 'Membership Status', 'woocommerce-memberships' ),
				/* translators: Cancelled Membership(s) */
				'label_count' => _n_noop( 'Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>', 'woocommerce-memberships' ),
			),

		);

		/**
		 * Filters user membership statuses.
		 *
		 * @since 1.0.0
		 *
		 * @param array $statuses associative array of statuses and their arguments
		 */
		return apply_filters( 'wc_memberships_user_membership_statuses', $statuses );
	}


	/**
	 * Checks if a string is a valid User Membership status.
	 *
	 * @since 1.10.0
	 *
	 * @param string $status
	 * @return bool
	 */
	public function is_user_membership_status( $status ) {

		$is_status = false;

		if ( ! empty( $status ) && $statuses = $this->get_user_membership_statuses() ) {

			// maybe add a 'wcm-' prefix
			$status = SV_WC_Helper::str_starts_with( $status, 'wcm-' ) ? $status : 'wcm-' . $status;

			$is_status = array_key_exists( $status, $statuses );
		}

		return $is_status;
	}


	/**
	 * Returns valid membership statuses to be considered as active.
	 *
	 * @since 1.7.0
	 *
	 * @return string[] array of statuses
	 */
	public function get_active_access_membership_statuses() {

		/**
		 * Filter user membership statuses that have access.
		 *
		 * @since 1.7.0
		 *
		 * @param string[] $statuses array of statuses
		 */
		return (array) apply_filters( 'wc_memberships_active_access_membership_statuses', array(
			'active',
			'complimentary',
			'pending',
		) );
	}


	/**
	 * Returns valid statuses for renewing a user membership on frontend.
	 *
	 * @since 1.7.0
	 *
	 * @return string[] array of statuses
	 */
	public function get_valid_user_membership_statuses_for_renewal() {

		/**
		 * Filter the valid statuses for renewing a user membership on frontend.
		 *
		 * @since 1.0.0
		 *
		 * @param array $statuses array of statuses valid for renewal
		 */
		return (array) apply_filters( 'wc_memberships_valid_membership_statuses_for_renewal', array(
			'active',
			'cancelled',
			'expired',
			'paused',
		) );
	}


	/**
	 * Returns valid statuses for cancelling a user membership from frontend.
	 *
	 * @since 1.7.0
	 *
	 * @return string[] array of statuses
	 */
	public function get_valid_user_membership_statuses_for_cancellation() {

		/**
		 * Filter the valid statuses for cancelling a user membership on frontend.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $statuses array of statuses valid for cancellation
		 */
		return (array) apply_filters( 'wc_memberships_valid_membership_statuses_for_cancel', array(
			'active',
			'delayed',
		) );
	}


	/**
	 * Adjusts a new user membership post data.
	 *
	 * @internal
	 *
	 * @since 1.6.0
	 *
	 * @param array $data original post data
	 * @return array $data modified post data
	 */
	public function adjust_user_membership_post_data( $data ) {

		if ( 'wc_user_membership' === $data['post_type'] ) {

			// password-protected user membership posts
			if ( ! $data['post_password'] ) {
				$data['post_password'] = uniqid( 'um_', false );
			}

			// make sure the passed in user ID is used as post author
			if ( isset( $_GET['user'] ) && 'auto-draft' === $data['post_status'] ) {
				$data['post_author'] = absint( $_GET['user'] );
			}
		}

		return $data;
	}


	/**
	 * Handles post status transitions for user memberships.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 *
	 * @param string $new_status new status slug
	 * @param string $old_status old status slug
	 * @param \WP_Post $post related WP_Post object
	 */
	public function transition_post_status( $new_status, $old_status, WP_Post $post ) {

		if ( 'wc_user_membership' !== $post->post_type || $new_status === $old_status ) {
			return;
		}

		// skip for new posts and auto drafts
		if ( 'new' === $old_status || 'auto-draft' === $old_status ) {
			return;
		}

		$user_membership = $this->get_user_membership( $post );

		$this->prune_object_caches( $user_membership );

		$old_status = str_replace( 'wcm-', '', $old_status );
		$new_status = str_replace( 'wcm-', '', $new_status );

		/* translators: Placeholders: Membership status changed from status A (%1$s) to status B (%2$s) */
		$status_note   = sprintf( __( 'Membership status changed from %1$s to %2$s.', 'woocommerce-memberships' ), wc_memberships_get_user_membership_status_name( $old_status ), wc_memberships_get_user_membership_status_name( $new_status ) );
		$optional_note = $this->get_membership_status_transition_note();

		// prepend optional note to status note, if provided
		$note = $optional_note ? $optional_note . ' ' . $status_note : $status_note;

		$user_membership->add_note( $note );

		switch ( $new_status ) {

			case 'cancelled':

				$user_membership->set_cancelled_date( current_time( 'mysql', true ) );
				$user_membership->unschedule_expiration_events();

			break;

			case 'expired':

				// loose check to see if this was a manually triggered expiration
				$end_date = $user_membership->get_end_date( 'timestamp' );

				// if manually expired, set expire date to now and reschedule expiration events (also when previously cancelled)
				if ( $end_date > 0 && current_time( 'timestamp', true ) < $end_date ) {
					$user_membership->set_end_date( current_time( 'mysql', true ) );
				} elseif ( 'cancelled' === $old_status ) {
					$user_membership->schedule_expiration_events( $user_membership->get_end_date( 'timestamp' ) );
				}

			break;

			case 'paused':

				$now = current_time( 'mysql', true );

				$user_membership->set_paused_date( $now );

				// delayed memberships should disregard intervals at all
				if ( 'delayed' !== $old_status ) {
					$user_membership->set_paused_interval( 'start', strtotime( $now ) );
				}

				// restore expiration events if the Membership was cancelled
				if ( 'cancelled' === $old_status ) {
					$user_membership->schedule_expiration_events( $user_membership->get_end_date( 'timestamp' ) );
				}

			break;

			case 'active':

				if ( 'delayed' === $old_status ) {

					// For sanity, delayed membership which are now active
					// shouldn't ever had any of these set.
					$user_membership->delete_paused_date();
					$user_membership->delete_paused_intervals();

				} elseif ( $user_membership->get_paused_date() ) {

					// Save the new membership end date and remove the paused date:
					// this means that if the membership was paused, or, for example,
					// paused and then cancelled, and then re-activated, the time paused
					// will be added to the expiry date, so that the end date is pushed back.
					$user_membership->set_end_date( $user_membership->get_end_date() );
					$user_membership->set_paused_interval( 'end', current_time( 'timestamp', true ) );
					$user_membership->delete_paused_date();

				} elseif ( 'cancelled' === $old_status ) {

					// restore expiration events if previously cancelled
					$user_membership->schedule_expiration_events( $user_membership->get_end_date( 'timestamp' ) );
				}

			break;

			default :

				// restore expiration events if the Membership was cancelled
				if ( 'cancelled' === $old_status ) {
					$user_membership->schedule_expiration_events( $user_membership->get_end_date( 'timestamp' ) );
				}

			break;

		}

		/**
		 * Fires when user membership status is updated.
		 *
		 * @since 1.0.0
		 *
		 * @param \WC_Memberships_User_Membership $user_membership the membership
		 * @param string $old_status old status, without the `wcm-` prefix
		 * @param string $new_status new status, without the `wcm-` prefix
		 */
		do_action( 'wc_memberships_user_membership_status_changed', $user_membership, $old_status, $new_status );

		$this->prune_object_caches( $user_membership );
	}


	/**
	 * Sets a user membership status transition note.
	 *
	 * Sets a note to be saved along with the general "status changed from %s to %s" note when the status of a user membership changes.
	 *
	 * @since 1.0.0
	 *
	 * @param string $note note content
	 */
	public function set_membership_status_transition_note( $note ) {

		$this->membership_status_transition_note = $note;
	}


	/**
	 * Returns the membership status transition note.
	 *
	 * Gets the note and resets it, so it does not interfere with any following status transitions.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 *
	 * @return string $note note content
	 */
	public function get_membership_status_transition_note() {

		$note = $this->membership_status_transition_note;

		$this->membership_status_transition_note = null;

		return $note;
	}


	/**
	 * Triggers user membership expiration events.
	 *
	 * @since 1.7.0
	 *
	 * @param array $args expiration event args
	 * @param string $force_event event to trigger, only when calling the method directly and not as hook callback
	 */
	public function trigger_expiration_events( $args, $force_event = '' ) {

		$user_membership_id = isset( $args['user_membership_id'] ) ? (int) $args['user_membership_id'] : $args;
		$current_filter     = ! empty( $force_event ) ? $force_event : current_filter();

		if ( ! is_numeric( $user_membership_id ) || empty( $current_filter ) ) {
			return;
		}

		// double-check to bail if not using action scheduler in case of old cron expiration events firing
		if ( 'wc_memberships_user_membership_expiry' === $current_filter && defined( 'DOING_CRON' ) ) {

			// find the event running if set
			$event = ActionScheduler::store()->find_action( $current_filter, array(
				'args'   => array( 'user_membership_id' => $user_membership_id ),
				'group'  => 'woocommerce-memberships',
				'status' => 'in-progress',
			) );

			// if not set, we know cron is calling this, not AS
			if ( ! $event ) {
				return;
			}
		}

		// you may fire when ready
		if ( $emails_instance = wc_memberships()->get_emails_instance() ) {

			if ( 'wc_memberships_user_membership_expiring_soon' === $current_filter ) {

				$emails_instance->send_membership_ending_soon_email( $user_membership_id );

			} elseif ( 'wc_memberships_user_membership_expiry' === $current_filter ) {

				$user_membership = $this->get_user_membership( $user_membership_id );

				if ( $user_membership ) {
					$user_membership->expire_membership();
				}

				$emails_instance->send_membership_ended_email( $user_membership_id );

			} elseif ( 'wc_memberships_user_membership_renewal_reminder' === $current_filter ) {

				$emails_instance->send_membership_renewal_reminder_email( $user_membership_id );
			}
		}
	}


	/**
	 * Callback for save_post when a user membership is created or updated.
	 *
	 * Triggers `wc_memberships_user_membership_saved` action.
	 * @see \wc_memberships_create_user_membership()
	 *
	 * @internal
	 *
	 * @since 1.3.8
	 *
	 * @param int $post_id the post ID
	 * @param WP_Post $post the post object
	 * @param bool $update whether we are updating or creating a new post
	 */
	public function save_user_membership( $post_id, $post, $update ) {

		if ( 'wc_user_membership' === get_post_type( $post ) && ( $user_membership = $this->get_user_membership( $post_id ) ) ) {

			/**
			 * Fires after a user has been granted membership access.
			 *
			 * This hook is similar to `wc_memberships_user_membership_created`,
			 * but will also fire when a membership is manually created in admin,
			 * or upon an import or via command line interface, etc.
			 * @see \wc_memberships_create_user_membership()
			 *
			 * @since 1.3.8
			 *
			 * @param \WC_Memberships_Membership_Plan $membership_plan the plan that user was granted access to.
			 * @param array $args
			 * @param array $args array of contextual arguments {
			 *     @type int $user_id the user ID the membership is assigned to.
			 *     @type int $user_membership_id the user membership id being saved.
			 *     @type bool $is_update whether this is a post update or a newly created membership.
			 * }
			 */
			do_action( 'wc_memberships_user_membership_saved', $user_membership->get_plan(), array(
				'user_id'            => $user_membership->get_user_id(),
				'user_membership_id' => $user_membership->get_id(),
				'is_update'          => $update,
			) );

			$this->prune_object_caches( $user_membership );
		}
	}


	/**
	 * Deletes related user memberships when a matching user is deleted.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id ID of a member being deleted
	 */
	public function delete_user_memberships( $user_id ) {

		$user_memberships = $this->get_user_memberships( $user_id );

		foreach ( $user_memberships as $membership ) {
			wp_delete_post( $membership->get_id() );
		}
	}


	/**
	 * Deletes related data when a user membership is deleted.
	 *
	 * @internal
	 *
	 * @since 1.7.0
	 *
	 * @param int $post_id post object ID of the user membership being deleted
	 */
	public function delete_related_data( $post_id ) {

		// bail out if the post being deleted is not a user membership
		if ( 'wc_user_membership' !== get_post_type( $post_id ) ) {
			return;
		}

		// delete scheduled events
		if ( $user_membership = $this->get_user_membership( $post_id ) ) {

			$user_membership->unschedule_expiration_events();

			$this->prune_object_caches( $user_membership );
		}
	}


	/**
	 * Cancels a user membership when the associated order is trashed.
	 *
	 * @internal
	 *
	 * @since 1.0.1
	 *
	 * @param int $order_id \WC_Order post ID of the order being trashed
	 */
	public function handle_order_trashed( $order_id ) {

		$this->handle_order_cancellation( $order_id, __( 'Membership cancelled because the associated order was trashed.', 'woocommerce-memberships' ) );
	}


	/**
	 * Cancels a user membership when the associated order is refunded.
	 *
	 * @internal
	 *
	 * @since 1.0.1
	 *
	 * @param int $order_id ID of the order being refunded
	 */
	public function handle_order_refunded( $order_id ) {

		$this->handle_order_cancellation( $order_id, __( 'Membership cancelled because the associated order was refunded.', 'woocommerce-memberships' ) );
	}


	/**
	 * Handles a cancellation due to an order event.
	 *
	 * @since 1.6.0
	 *
	 * @param int $order_id order ID associated to the User Membership
	 * @param string $note cancellation message
	 */
	private function handle_order_cancellation( $order_id, $note ) {

		if ( 'shop_order' !== get_post_type( $order_id ) ) {
			return;
		}

		if ( $user_memberships = $this->get_user_membership_by_order_id( $order_id ) ) {

			foreach ( $user_memberships as $user_membership ) {
				$user_membership->cancel_membership( $note );
			}
		}
	}


	/**
	 * Excludes user membership notes from queries and RSS feeds.
	 *
	 * @internal
	 *
	 * @since 1.9.0
	 *
	 * @param array $clauses
	 * @return array
	 */
	public function exclude_membership_notes_from_queries( $clauses ) {
		global $wpdb, $typenow;

		// don't hide when viewing user memberships in admin
		if ( 'wc_user_membership' === $typenow && is_admin() && current_user_can( 'manage_woocommerce' ) ) {
			return $clauses;
		}

		if ( ! $clauses['join'] ) {
			$clauses['join'] = '';
		}

		if ( false === strpos( $clauses['join'], "JOIN $wpdb->posts" ) ) {
			$clauses['join'] .= " LEFT JOIN $wpdb->posts ON comment_post_ID = $wpdb->posts.ID ";
		}

		if ( $clauses['where'] ) {
			$clauses['where'] .= ' AND ';
		}

		$clauses['where'] .= " $wpdb->posts.post_type <> 'wc_user_membership' ";

		return $clauses;
	}


	/**
	 * Excludes user membership notes from queries and RSS feeds.
	 *
	 * @internal
	 *
	 * @since 1.9.0
	 *
	 * @param string $join
	 * @return string
	 */
	public function exclude_membership_notes_from_feed_join( $join ) {
		global $wpdb;

		if ( ! strstr( $join, $wpdb->posts ) ) {
			$join = " LEFT JOIN $wpdb->posts ON $wpdb->comments.comment_post_ID = $wpdb->posts.ID ";
		}

		return $join;
	}


	/**
	 * Excludes user membership notes from queries and RSS feeds.
	 *
	 * @internal
	 *
	 * @since 1.9.0
	 *
	 * @param string $where
	 * @return string
	 */
	public function exclude_membership_notes_from_feed_where( $where ) {
		global $wpdb;

		if ( $where ) {
			$where .= ' AND ';
		}

		$where .= " $wpdb->posts.post_type <> 'wc_user_membership' ";

		return $where;
	}


	/**
	 * Excludes user membership notes from the comments count totals.
	 *
	 * @internal
	 *
	 * @since 1.10.1
	 *
	 * @param \stdClass $counts comment counts stored in an object
	 * @param int $post_id optional, whether the counts are related to a specific post and not global (0)
	 * @return \stdClass
	 */
	public function exclude_membership_notes_from_comments_count( $counts, $post_id = 0 ) {

		if ( 0 === $post_id ) {

			$notes = $this->get_user_membership_notes_count();

			if ( $notes > 0 ) {
				$counts->all      = max( 0, (int) $counts->all - $notes );
				$counts->approved = max( 0, (int) $counts->approved - $notes );
			}

		} elseif ( is_numeric( $post_id ) && $post_id > 0 && 'wc_user_membership' === get_post_type( $post_id ) ) {

			$new_counts = new stdClass();

			foreach ( array_keys( (array) $counts ) as $count ) {
				$new_counts->$count = 0;
			}

			$counts = $new_counts;
		}

		return $counts;
	}


	/**
	 * Backwards compatibility handler for deprecated methods.
	 *
	 * TODO remove deprecated methods when they are at least minor versions older (as in x.Y.z semantic versioning) {FN 2017-23-06}
	 *
	 * @since 1.7.0
	 *
	 * @param string $method method called
	 * @param void|string|array|mixed $args optional argument(s)
	 * @return null|void|mixed
	 */
	public function __call( $method, $args ) {

		$called = "wc_memberships()->get_user_memberships()->{$method}()";

		switch ( $method ) {

			/** @deprecated since 1.8.0 - remove by 1.11.0 or higher */
			case 'is_user_non_inactive_member' :

				_deprecated_function( $called, '1.8.0', 'wc_memberships_is_user_active_or_delayed_member()' );

				$user_id = isset( $args[0] ) ? $args[0] : null;
				$plan    = isset( $args[1] ) ? $args[1] : null;

				return $this->is_user_active_or_delayed_member( $user_id, $plan );
		}

		// you're probably doing it wrong
		trigger_error( 'Call to undefined property ' . __CLASS__ . '::' . $method, E_USER_ERROR );
		return null;
	}


}
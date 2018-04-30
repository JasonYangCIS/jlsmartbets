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
 * Job handler to grant access to membership plans retroactively.
 *
 * @since 1.10.0
 */
class WC_Memberships_Grant_Retroactive_Access extends WC_Memberships_Job_Handler {


	/**
	 * Sets up the job handler.
	 *
	 * @since 1.10.0
	 */
	public function __construct() {

		$this->action   = 'grant_retroactive_access';
		$this->data_key = 'user_ids';

		parent::__construct();

		add_action( "{$this->identifier}_job_complete", array( $this, 'cleanup_jobs' ), 1 );
		add_action( "{$this->identifier}_job_failed",   array( $this, 'cleanup_jobs' ), 1 );
	}


	/**
	 * Returns a job object.
	 *
	 * @since 1.10.0
	 *
	 * @param null|int|string|\stdClass|\WC_Memberships_Membership_Plan $id a membership plan ID, or a job identifier (string or object)
	 * @return null|\stdClass
	 */
	public function get_job( $id = null ) {

		// for this task we may pass a plan ID (integer) or a plan object to retrieve the corresponding job,
		// since each job should be unique per plan and only one job per plan is allowed to run at one time
		if ( $id instanceof WC_Memberships_Membership_Plan || is_numeric( $id ) ) {

			$plan_id  = $id instanceof WC_Memberships_Membership_Plan ? $id->get_id() : (int) $id;
			$jobs     = $plan_id > 0 ? $this->get_jobs() : array();
			$plan_job = null;

			if ( ! empty( $jobs ) ) {

				foreach ( $jobs as $job ) {

					if ( isset( $job->membership_plan_id ) && $job->membership_plan_id > 0 && $plan_id === (int) $job->membership_plan_id ) {

						$plan_job = $job;
						break;
					}
				}
			}

		} else {

			// otherwise, retrieve the job normally by job ID or object
			$plan_job = parent::get_job( $id );
		}

		return $plan_job;
	}


	/**
	 * Checks whether there is an ongoing job.
	 *
	 * The assumption is that there should be only one job at the time, if it's processing it must be the current one.
	 *
	 * @since 1.10.0
	 *
	 * @param string|int $id job ID or membership plan ID
	 * @return bool
	 */
	public function has_ongoing_job( $id = null ) {

		$job = $this->get_job( $id );

		return $job && isset( $job->status ) && 'processing' !== $job->status;
	}


	/**
	 * Deletes a job.
	 *
	 * @since 1.10.0
	 *
	 * @param \stdClass|string|int|\WC_Memberships_Membership_Plan|null $job a job or plan identifier
	 * @return bool
	 */
	public function delete_job( $job ) {

		if ( is_numeric( $job ) ) {
			$job = $this->get_job( $job );
		}

		return $job && parent::delete_job( $job );
	}


	/**
	 * Grants access to users in background.
	 *
	 * @since 1.10.0
	 *
	 * @param \stdClass $job job object
	 * @param int $items_per_batch items to process per batch
	 * @return false|\stdClass job object or false on error
	 * @throws \SV_WC_Plugin_Exception
	 */
	public function process_job( $job, $items_per_batch = 5 ) {

		$items_per_batch = $this->get_items_per_batch( $items_per_batch, $job );

		if ( ! $this->start_time ) {
			$this->start_time = time();
		}

		// indicate that the job has started processing
		if ( 'processing' !== $job->status ) {

			$job->status                = 'processing';
			$job->started_processing_at = current_time( 'mysql' );

			$job = $this->update_job( $job );
		}

		$data_key = $this->data_key;

		// we need users to loop to check if they can be granted access
		if ( ! isset( $job->{$data_key} ) || ! is_array( $job->{$data_key} ) ) {

			$this->fail_job( $job );

			throw new SV_WC_Plugin_Exception( esc_html__( 'Users to look for granting access to a plan not defined or invalid.', 'woocommerce-memberships' ) );
		}

		// we need a membership plan ID to give access to
		if ( ! isset( $job->membership_plan_id ) || ! is_numeric( $job->membership_plan_id ) ) {

			$this->fail_job( $job );

			throw new SV_WC_Plugin_Exception( sprintf( esc_html__( 'Membership Plan to grant access to not defined or invalid.', 'woocommerce-memberships' ), 'membership_plan_id' ) );
		}

		/* @type int[] $user_ids array of user IDs */
		$user_ids = $job->{$data_key};

		$job->total = count( $user_ids );

		// skip already processed items
		if ( $job->progress && ! empty( $user_ids ) ) {
			$user_ids = array_slice( $user_ids, $job->progress, null, true );
		}

		// loop over unprocessed items and process them
		if ( ! empty( $user_ids ) ) {

			$membership_plan = wc_memberships_get_membership_plan( $job->membership_plan_id );
			$processed_users = 0;

			foreach ( $user_ids as $user_id ) {

				$this->process_item( $user_id, $membership_plan );

				$processed_users++;

				// job limits reached
				if ( $processed_users >= $items_per_batch || $this->time_exceeded() || $this->memory_exceeded() ) {
					break;
				}
			}

			$job->progress  += $processed_users;
			$job->percentage = $this->get_percentage( $job );

			// update job progress
			$job = $this->update_job( $job );

		} else {

			// if there are no more users to process, then we're done
			$job->progress   = $job->total;
			$job->percentage = $this->get_percentage( $job );
		}

		// complete current job
		if ( $job->progress >= $job->total ) {
			$job = $this->complete_job( $job );
		}

		return $job;
	}


	/**
	 * Process one user to grant membership access.
	 *
	 * @since 1.10.0
	 *
	 * @param int $user_id the ID of the user to grant a membership to
	 * @param \WC_Memberships_Membership_Plan $membership_plan the plan to grant access to
	 * @return null|\WC_Memberships_User_Membership the created user membership or null on skip or insuccess
	 */
	public function process_item( $user_id, $membership_plan ) {

		$user_membership = null;

		if ( $membership_plan instanceof WC_Memberships_Membership_Plan && is_numeric( $user_id ) ) {

			$user = get_user_by( 'id', (int) $user_id );

			if ( $user instanceof WP_User && 'publish' === $membership_plan->post->post_status ) {

				switch ( $membership_plan->get_access_method() ) {

					// grant access to users who have purchased in the past at least one product that should grant access
					case 'purchase' :
						$user_membership = $this->grant_access_to_existing_purchases( $user, $membership_plan );
					break;

					// free plan: grant access to users for the simple act of having signed up
					case 'signup' :
						$user_membership = $this->grant_free_access_to_existing_user( $user, $membership_plan );
					break;
				}
			}
		}

		return $user_membership;
	}


	/**
	 * Retroactively grants a user membership to registered users for a signup-access membership plan.
	 *
	 * @since 1.10.0
	 *
	 * @param \WP_User|int $user user to grant access to
	 * @param \WC_Memberships_Membership_Plan $membership_plan Membership Plan the user would access to
	 * @return null|\WC_Memberships_User_Membership the newly created user membership on success
	 */
	private function grant_free_access_to_existing_user( $user, $membership_plan ) {

		$user_membership = null;

		/**
		 * Filters whether existing users can be retroactively granted access to free membership plans created after a user registration occurred.
		 *
		 * @since 1.7.0
		 *
		 * @param bool $grant_access whether to grant access (default true if the user is not a member or expired member already)
		 * @param array $args
		 */
		$grant_access = (bool) apply_filters( 'wc_memberships_grant_access_to_existing_user', ! wc_memberships_is_user_member( $user, $membership_plan ), array(
			'user_id'    => $user instanceof WP_User ? $user->ID : $user,
			'plan_id'    => $membership_plan->get_id(),
		) );

		if ( $grant_access ) {
			$user_membership = wc_memberships()->get_plans_instance()->grant_access_to_free_membership( $user, false, $membership_plan );
		}

		/**
		 * Applies when a user is being processed for granting access to sign up plans retroactively.
		 *
		 * @since 1.10.1
		 *
		 * @param \WC_Memberships_User_Membership|null $user_membership a user membership, if access was granted, or null if not
		 * @param \WP_User $user an user to grant access to a plan (may be a member already)
		 * @param \WC_Memberships_Membership_Plan $membership_plan the sign up plan we are granting access to
		 */
		return apply_filters( 'wc_memberships_granted_free_access_from_previous_signup', $user_membership, $user, $membership_plan );
	}


	/**
	 * Grants access to a non-free membership plan to users which have previously purchased a product that grants access.
	 *
	 * @since 1.10.0
	 *
	 * @param \WP_User $user the user to grant access to
	 * @param \WC_Memberships_Membership_Plan $membership_plan Membership Plan to grant user access to
	 * @return null|\WC_Memberships_User_Membership the newly created user membership on success
	 */
	private function grant_access_to_existing_purchases( $user, $membership_plan ) {

		$user_membership = null;
		$product_ids     = $membership_plan->get_product_ids();

		if ( ! empty( $product_ids ) ) {

			// backwards compatible WC core filtering of paid statuses
			if ( SV_WC_Plugin_Compatibility::is_wc_version_gte_3_0() ) {
				$paid_statuses = wc_get_is_paid_statuses();
			} else {
				/* replicates core WooCommerce filter that didn't exist prior to version 3.0 to mark paid order statuses */
				$paid_statuses = apply_filters( 'woocommerce_order_is_paid_statuses', array( 'completed', 'processing' ) );
			}

			/**
			 * Filters the array of valid order statuses that grant access.
			 *
			 * Allows to include additional custom order statuses that should grant access when the admin uses the "grant previous purchases access" action.
			 *
			 * @since 1.0.0
			 *
			 * @param array $valid_order_statuses_for_grant array of order statuses
			 * @param \WC_Memberships_Membership_Plan $membership_plan the associated membership plan object
			 */
			$statuses = (array) apply_filters( 'wc_memberships_grant_access_from_existing_purchase_order_statuses', $paid_statuses, $membership_plan );

			if ( ! empty( $statuses ) ) {

				/* @type \WC_Order[] $orders get all orders for the current user with a status suited for membership activation */
				$orders = wc_get_orders( array(
					'customer' => $user->ID,
					'status'   => $statuses,
				) );

				if ( ! empty( $orders ) ) {

					$cumulative_access_allowed = wc_memberships_cumulative_granting_access_orders_allowed();

					foreach ( $orders as $order ) {

						/* @type array|\WC_Order_Item[] $items */
						$items = $order instanceof WC_Order ? $order->get_items() : null;

						if ( ! empty( $items ) ) {

							foreach ( $items as $item ) {

								$product = $order->get_product_from_item( $item );

								if ( $product && in_array( $product->get_id(), $product_ids, false ) ) {

									// if membership extensions by cumulative purchases are enabled grant access if the order didn't grant access before
									if ( $cumulative_access_allowed ) {
										$user_membership = wc_memberships_get_user_membership( $user, $membership_plan );
										$grant_access    = ! ( $user_membership && wc_memberships_has_order_granted_access( $order, array( 'user_membership' => $user_membership ) ) );
									// if, instead, cumulative granting access orders are disallowed, grant access if user is not already a member
									} else {
										$grant_access    = ! wc_memberships_is_user_member( $user, $membership_plan, false );
									}

									/**
									 * Filters whether an existing purchase of the product should grant access to the membership plan or not.
									 *
									 * Allows third party code to override if a previously purchased product should retroactively grant access to a membership plan or not.
									 *
									 * @since 1.0.0
									 *
									 * @param bool $grant_access whether grant access from existing purchase
									 * @param array $args array of arguments connected with the access request
									 */
									$grant_access = (bool) apply_filters( 'wc_memberships_grant_access_from_existing_purchase', $grant_access, array(
										'user_id'    => $user->ID,
										'product_id' => $product->get_id(),
										'order_id'   => $order->get_id(),
										'plan_id'    => $membership_plan->get_id(),
									) );

									if ( $grant_access ) {
										$user_membership = $membership_plan->grant_access_from_purchase( $user->ID, $product->get_id(), $order->get_id() );
									}
								}
							}
						}
					}
				}
			}

			/**
			 * Applies when a user is being processed for granting access to their existing purchases.
			 *
			 * @since 1.10.1
			 *
			 * @param \WC_Memberships_User_Membership|null $user_membership a user membership, if access was granted, or null, if not
			 * @param \WP_User $user an user we are evaluating whether to grant access to a plan (may be a member already)
			 * @param \WC_Memberships_Membership_Plan $membership_plan the plan we are granting access to
			 */
			$user_membership = apply_filters( 'wc_memberships_granted_access_from_existing_purchase', $user_membership, $user, $membership_plan );
		}

		return $user_membership;
	}


}

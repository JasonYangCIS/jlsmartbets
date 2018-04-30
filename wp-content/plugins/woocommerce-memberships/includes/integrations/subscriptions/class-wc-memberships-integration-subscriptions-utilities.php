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
 * Subscriptions Integration Utilities handler.
 *
 * @since 1.10.0
 */
class WC_Memberships_Integration_Subscriptions_Utilities {


	/** @var \WC_Memberships_Integration_Subscriptions_Utilities_Activation_Background_Job instance */
	private $activation_background;


	/**
	 * Loads Subscriptions integration utilities.
	 *
	 * Background jobs and batch jobs handlers need to be always loaded to run properly.
	 *
	 * @since 1.10.0
	 */
	public function __construct() {

		$this->activation_background = wc_memberships()->load_class( '/includes/integrations/subscriptions/class-wc-memberships-integration-subscriptions-utilities-activation-background-job.php', 'WC_Memberships_Integration_Subscriptions_Utilities_Activation_Background_Job' );

		// subscription id CSV export
		add_filter( 'wc_memberships_csv_export_user_memberships_headers',                array( $this, 'export_user_membership_headers_add_subscription_id' ) );
		add_filter( 'wc_memberships_csv_export_user_memberships_subscription_id_column', array( $this, 'export_user_membership_subscription_id' ), 10, 3 );
		// subscription id CSV import
		add_filter( 'wc_memberships_csv_import_user_memberships_data', array( $this, 'import_user_membership_data' ), 10, 4 );
		add_action( 'wc_memberships_csv_import_user_membership',       array( $this, 'import_user_membership_subscription_id' ), 10, 4 );
	}


	/**
	 * Returns the subscription based memberships activation background handler.
	 *
	 * @since 1.10.0
	 *
	 * @return \WC_Memberships_Integration_Subscriptions_Utilities_Activation_Background_Job
	 */
	public function get_activation_background_instance() {
		return $this->activation_background;
	}


	/**
	 * Adds a subscription_id column to CSV export headers
	 *
	 * @internal
	 *
	 * @since 1.10.1
	 *
	 * @param array $headers
	 * @return array
	 */
	public function export_user_membership_headers_add_subscription_id( array $headers ) {

		if ( isset( $headers['product_id'] ) ) {
			$headers = SV_WC_Helper::array_insert_after( $headers, 'product_id', array(
				'subscription_id' => 'subscription_id',
			) );
		} else {
			$headers['subscription_id'] = 'subscription_id';
		}

		return $headers;
	}


	/**
	 * Exports the subscription ID in Memberships CSV Export.
	 *
	 * @internal
	 *
	 * @since 1.10.1
	 *
	 * @param string $value the value for the CSV column in output
	 * @param string $column the matching CSV column
	 * @param \WC_Memberships_User_Membership $user_membership the User Membership being exported
	 * @return string
	 */
	public function export_user_membership_subscription_id( $value = '', $column, $user_membership ) {

		$subscription_id = null;

		if ( 'subscription_id' === $column && $user_membership instanceof WC_Memberships_User_Membership ) {

			$integration     = wc_memberships()->get_integrations_instance()->get_subscriptions_instance();
			$subscription_id = $integration ? $integration->get_user_membership_subscription_id( $user_membership->get_id() ) : null;
		}

		return is_numeric( $subscription_id ) ? (int) $subscription_id : $value;
	}


	/**
	 * Adds a Subscription ID to be added to the data to be processed on a import with Memberships Import.
	 *
	 * @internal
	 *
	 * @since 1.10.1
	 *
	 * @param array $import_data data to import to create or update a membership
	 * @param string $action create or merge a membership
	 * @param array $columns the CSV columns
	 * @param array $row the CSV row being processed
	 * @return array data
	 */
	public function import_user_membership_data( array $import_data, $action, array $columns, array $row ) {

		$subscription_id = null;

		if ( isset( $columns['subscription_id'] ) ) {
			$subscription_id = ! empty( $row['subscription_id'] ) ? trim( $row['subscription_id'] ) : null;
		} elseif ( isset( $columns['subscription'] ) ) {
			$subscription_id = ! empty( $row['subscription'] )    ? trim( $row['subscription'] )    : null;
		}

		if ( is_numeric( $subscription_id ) && $subscription_id > 0 ) {
			$import_data['subscription_id'] = (int) $subscription_id;
		}

		return $import_data;
	}


	/**
	 * Imports the subscription ID for a User Membership when using Memberships Import.
	 *
	 * @internal
	 *
	 * @since 1.10.1
	 *
	 * @param \WC_Memberships_User_Membership $user_membership the user membership
	 * @param string $action either 'create' or 'renew'
	 * @param array $import_data import data
	 * @param \stdClass $import_job import job
	 */
	public function import_user_membership_subscription_id( $user_membership, $action, $import_data, $import_job ) {

		$subscription_membership = new WC_Memberships_Integration_Subscriptions_User_Membership( ! empty( $user_membership->post ) && $user_membership->post instanceof WP_Post ? $user_membership->post : $user_membership->get_id() );

		if ( $subscription_membership->get_id() > 0 ) {

			$already_tied = (int) $subscription_membership->get_subscription_id() > 0;

			// check conditions for which we are allowed to set or update a subscription ID
			if ( ( ! $already_tied && 'create' === $action ) || ( 'merge' === $action && $import_job->merge_existing_memberships ) ) {

				$subscription_id = isset( $import_data['subscription_id'] ) && is_numeric( $import_data['subscription_id'] ) ? (int) $import_data['subscription_id'] : 0;

				// check if we have a subscription id and it belongs to a subscription
				if ( $subscription_id > 0 && ( $subscription = wcs_get_subscription( $subscription_id ) ) ) {

					$subscription_membership->set_subscription_id( $subscription_id );

					// maybe set the trial end date on the membership
					if ( $trial_end = wc_memberships()->get_integrations_instance()->get_subscriptions_instance()->get_subscription_event_date( $subscription, 'trial_end' ) ) {
						$subscription_membership->set_free_trial_end_date( $trial_end );
					}

				} else {

					$subscription_membership->delete_subscription_id();
				}
			}
		}
	}


}
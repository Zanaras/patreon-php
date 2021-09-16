<?php
namespace Patreon;

class API {
	
	// Holds the access token
	private $access_token;

	// Holds the api endpoint used
	private $api_endpoint;

	// The cache for request results - an array that matches md5 of the unique API request to the returned result
	public $request_cache;

	// Sets the reqeuest method for cURL
	private $api_request_method;

	// Holds POST for cURL for requests other than GET
	private $curl_postfields;

	// Sets the format the return from the API is parsed and returned - array (assoc), object, or raw JSON
	private $api_return_format;


	public function __construct($access_token, $api_endpoint = "https://www.patreon.com/api/oauth2/v2/", $api_request_method = 'GET', $api_return_format = 'array', $curl_postfields = false) {

		// Set the access token
		$this->access_token = $access_token;

		// Set API endpoint to use. Its currently V2
		$this->api_endpoint = $api_endpoint;

		// Set default return format
		$this->api_return_format = $api_return_format;

		// Set curl post fields flag
		$this->curl_postfields = $curl_postfields;

		// Set API request method
		$this->api_request_method = $api_request_method;

	}

	public function fetch_user( $args = [] ) {

		$starter = 'identity?';
		if (isset($args['membership'])) {
			# If this key exists, we're redefining the default request and we must want membership data.
			$suffix = $starter . 'include=memberships&fields'.urlencode('[user]').'=';
			if (isset($args['user'])) {
				# If this key  also exists, we're further redefining the default request.
				$suffix .= $args['user'] . '&fields'.urlencode('[member]').'=';
			} else {
				$suffix .= 'email,first_name,full_name,image_url,last_name,thumb_url,url,vanity,is_email_verified&fields'.urlencode('[member]').'=';
			}
			$suffix .= $args['membership'];
		} elseif (isset($args['user'])) {
			# If this key exists and 'membership' doesn't, then we check if we also want membership data.
			$suffix = $starter;
			if (!isset($args['no_membership']) || $args['no_membership']) {
				# If it doesn't exist, or it is set to false, we want membership data, so we request that.
				$suffix .= 'include=memberships&fields'.urlencode('[user]').'=';
				# Set flag for prepping membership call to true.
				$membership = true;
			} else {
				# We don't want membership, set flag.
				$membership = false;
			}
			$suffix .= 'fields'.urlencode('[user]').'=' . $args['user'];
			if (!$membership) {
				$suffix .= '&fields'.urlencode('[member]').'=campaign_lifetime_support_cents,currently_entitled_amount_cents,is_follower,last_charge_date,last_charge_status,lifetime_support_cents,next_charge_date,patron_status,pledge_cadence,pledge_relationship_start,will_pay_amount_cents';
			}
		} else {
			# Default request, grab everything that isn't bandwidth heavy (like user profiles).
			$suffix = 'identity?include=memberships&fields'.urlencode('[user]').'=can_see_nsfw,email,first_name,full_name,hide_pledges,image_url,is_email_verified,last_name,thumb_url,url,vanity&fields'.urlencode('[member]').'=campaign_lifetime_support_cents,currently_entitled_amount_cents,is_follower,last_charge_date,last_charge_status,lifetime_support_cents,next_charge_date,patron_status,pledge_cadence,pledge_relationship_start,will_pay_amount_cents';
			$suffix = 'identity?include=memberships,memberships.campaign,memberships.currently_entitled_tiers&fields' .urlencode('[user]'). '=can_see_nsfw,email,first_name,full_name,hide_pledges,image_url,is_email_verified,last_name,thumb_url,url,vanity&fields' .urlencode('[member]'). '=campaign_lifetime_support_cents,currently_entitled_amount_cents,is_follower,last_charge_date,last_charge_status,lifetime_support_cents,next_charge_date,patron_status,pledge_cadence,pledge_relationship_start,will_pay_amount_cents&fields' .urlencode('[campaign]'). '=is_monthly&fields' .urlencode('[tier]'). '=amount_cents,discord_role_ids,published,remaining,requires_shipping,title,unpublished_at,url,user_limit';
		}
		// Fetches details of the current token user.
		return $this->get_data($suffix, $args);
	}

	public function fetch_detailed_user( $default = true, $args = [] ) {
		# This function is a more advanced version of the fetch_user function above,
		# that requests different data and also tells the get_data function to pass data to a parser before retruning.
		# Keep in mind, the keys it looks for in args aren't "use this" but rather keys for "request these fields".

		$suffix = 'identity?';
		$member = false;
		$campaign = false;
		$categories = false;
		$tiers = false;
		$benefits = false;
		$goals = false;
		$users = false;

		# $default is used as a bypass, and just requests everything.
		# $args['override'] is an override, and just uses the string provided as the API request suffix.
		if (isset($args['override'])) {
			$suffix = $args['override'];
		} elseif ($default != true) {
			$include = false;
			if (isset($args['membership'])) {
				$suffix .= 'include=membership';
				$include = true;

				# The below relations rely on the above scope, so we nest them within.
				#If the below is requested without the above, we ignore it as there's no association to request against.
				if (isset($args['membership_campaign'])) {
					$suffix .= ',memberships.campaign';
					if (isset($args['membership_campaign_benefits'])) {
						$suffix .= ',memberships.campaign.benefits';
					}
					if (isset($args['membership_campaign_creator'])) {
						$suffix .= ',memberships.campaign.creator';
					}
					if (isset($args['membership_campaign_goals'])) {
						$suffix .= ',memberships.campaign.goals';
					}
					if (isset($args['membership_campaign_tiers'])) {
						$suffix .= ',memberships.campaign.tiers';
					}
				}
				if (isset($args['membership_tiers'])) {
					$suffix .= ',memberships.currently_entitled_tiers';
				}
				if (isset($args['membership_pledge_history'])) {
					$suffix .= ',memberships.pledge_history';
				}
			}
			if (isset($args['campaign'])) {
				if (!$include) {
					$suffix .= 'include=campaign';
					$include = true;
				} else {
					$suffix .= ',campaign';
				}

				# The below scopes rely on the above scope, so we nest them within.
				#If the below is requested without the above, we ignore it as there's no association to request against.
				if (isset($args['campaign_benefits'])) {
					$suffix .= ',campaign.benefits';
				}
				if (isset($args['campaign_categroies'])) {
					$suffix .= ',campaign.categories';
				}
				if (isset($args['campaign_creator'])) {
					$suffix .= ',campaign.creator';
				}
				if (isset($args['campaign_goals'])) {
					$suffix .= ',campaign.goals';
				}
				if (isset($args['campaign_tiers'])) {
					$suffix .= ',campaign.tiers';
				}

			}
			if (isset($args['user'])) {
				$suffix .= '&fields' . urlencode('[user]') . '=' . $args['user'];
				$users = true;
			}
			if ($include) {
				if (isset($args['membership'])) {
					$suffix .= '&fields' . urlencode('[member]') . $args['membership'];
					$members = true; # Set flag that we've already decalred fields.
					# Once again, if we don't have membership, we aren't requesting the below.
					if (isset($args['membership_campaign'])) {
						$suffix .= '&fields' . urlencode('[campaign]') . $args['membership_campaign'];
						$campaign = true;
						# And if we don't request the above, we can't request these.
						if (isset($args['membership_campaign_benefits'])) {
							$suffix .= '&fields' . urlencode('[benefit]') .$args['membership_campaign_benefits'];
							$benefits = true;
						}
						if (isset($args['membership_campaign_categroies'])) {
							$suffix .= '&fields' . urlencode('[category]') . $args['membership_campaign_categroies'];
							$categories = true;
						}
						if (isset($args['membership_campaign_creator']) && !$users) {
							$suffix .= '&fields' . urlencode('[user]') . $args['membership_campaign_creator'];
						}
						if (isset($args['membership_campaign_goals'])) {
							$suffix .= '&fields' . urlencode('[goal]') . $args['membership_campaign_goals'];
							$goals = true;
						}
						if (isset($args['membership_campaign_tiers'])) {
							$suffix .= '&fields' . urlencode('[tier]') . $args['membership_campaign_tiers'];
							$tiers = true;
						}
					}
					if (isset($args['membership_tiers']) && !$tiers) {
						$suffix .= '&fields' . urlencode('[tier]') . $args['membership_tiers'];
					}
					if (isset($args['membership_pledge_history'])) {
						$suffix .= '&fields' . urlencode('[pledge_event]') . $args['membership_pledge_history'];
					}
				}
				if (isset($args['campaign'])) {
					# If we've already passed campaign fields, we don't need to again.
					# This is separated so we can also check for relations.
					if (!$campaign) {
						$suffix .= '&fields' . urlencode('[campaign]') . $args['campaign'];
						$campaign = true;
					}
					# The below scopes rely on the above scope, so we nest them within.
					#If the below is requested without the above, we ignore it as there's no association to request against.
					if (isset($args['campaign_benefits']) && !$benefits) {
						$suffix .= '&fields' . urlencode('[benefit]') .$args['campaign_benefits'];
					}
					if (isset($args['campaign_categroies']) && !$categories) {
						$suffix .= '&fields' . urlencode('[category]') . $args['campaign_categroies'];
					}
					if (isset($args['campaign_creator']) && !$users) {
						$suffix .= '&fields' . urlencode('[user]') . $args['campaign_creator'];
					}
					if (isset($args['campaign_goals']) && !$goals) {
						$suffix .= '&fields' . urlencode('[goal]') . $args['campaign_goals'];
					}
					if (isset($args['campaign_tiers']) && !$tiers) {
						$suffix .= '&fields' . urlencode('[tier]') . $args['campaign_tiers'];
					}
				}
			}
		} else {
			# Default request, grab the common things, like tier amounts, last payment date, next paymenet date, basic user profile information, tier data, etc.
			$suffix = 'identity?include=memberships,memberships.campaign,memberships.currently_entitled_tiers&fields' .urlencode('[user]'). '=can_see_nsfw,email,first_name,full_name,hide_pledges,image_url,is_email_verified,last_name,thumb_url,url,vanity&fields' .urlencode('[member]'). '=campaign_lifetime_support_cents,currently_entitled_amount_cents,is_follower,last_charge_date,last_charge_status,lifetime_support_cents,next_charge_date,patron_status,pledge_cadence,pledge_relationship_start,will_pay_amount_cents&fields' .urlencode('[campaign]'). '=is_monthly&fields' .urlencode('[tier]'). '=amount_cents,discord_role_ids,published,remaining,requires_shipping,title,unpublished_at,url,user_limit';
		}
		// Fetches details of the current token user.
		return $this->get_data($suffix, $args);
	}

	public function fetch_campaigns( $args = [] ) {
		// Fetches the list of campaigns of the current token user. Requires the current user to be creator of the campaign or requires a creator access token
		return $this->get_data("campaigns");
	}

	public function fetch_campaign_details($campaign_id, $args = [] ) {
		// Fetches details about a campaign - the membership tiers, benefits, creator and goals.  Requires the current user to be creator of the campaign or requires a creator access token
		$suffix = 'campaigns/{' . $campaign_id . '}?include=benefits,creator,goals,tiers';
		if ($args['campaign']) {
			$suffix = 'campaigns/{' . $campaign_id . '}?include=' . $args['campaign'];
		}
		return $this->get_data($suffix);
	}

	public function fetch_member_details($member_id, $args = array() ) {
		// Fetches details about a member from a campaign. Member id can be acquired from fetch_page_of_members_from_campaign
		// currently_entitled_tiers is the best way to get info on which membership tiers the user is entitled to.  Requires the current user to be creator of the campaign or requires a creator access token.
		return $this->get_data("members/{$member_id}?include=address,campaign,user,currently_entitled_tiers");
	}

	public function fetch_page_of_members_from_campaign($campaign_id, $page_size, $cursor = null) {

		// Fetches a given page of members with page size and cursor point. Can be used to iterate through lists of members for a given campaign. Campaign id can be acquired from fetch_campaigns or from a saved campaign id variable.  Requires the current user to be creator of the campaign or requires a creator access token
		$url = "campaigns/{$campaign_id}/members?page%5Bsize%5D={$page_size}";

		if ($cursor != null) {

		  $escaped_cursor = urlencode($cursor);
		  $url = $url . "&page%5Bcursor%5D={$escaped_cursor}";

		}

		return $this->get_data($url);

	}

	public function get_data( $suffix, $args = array() ) {

		// Construct request:
		$api_request = $this->api_endpoint . $suffix;

		// This identifies a unique request
		$api_request_hash = md5( $this->access_token . $api_request );

		// Check if this request exists in the cache and if so, return it directly - avoids repeated requests to API in the same page run for same request string

		if ( !isset( $args['skip_read_from_cache'] ) ) {
			if ( isset( $this->request_cache[$api_request_hash] ) ) {
				return $this->request_cache[$api_request_hash];
			}
		}

		// Request is new - actually perform the request

		$ch = $this->__create_ch($api_request);
		$json_string = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);

		// don't try to parse a 500-class error, as it's likely not JSON
		if ( $info['http_code'] >= 500 ) {
			if ( !isset( $args['skip_add_to_cache']) ) {
				return $this->add_to_request_cache($api_request_hash, $json_string);
			} else {
				return $json_string;
			}
		}

		// don't try to parse a 400-class error, as it's likely not JSON
		if ( $info['http_code'] >= 400 ) {
			if ( !isset( $args['skip_add_to_cache']) ) {
				return $this->add_to_request_cache($api_request_hash, $json_string);
			} else {
				return $json_string;
			}
		}

		// Parse the return according to the format set by api_return_format variable

		if( $this->api_return_format == 'array' ) {
			$return = json_decode($json_string, true);
		} elseif ( $this->api_return_format == 'object' ) {
			$return = json_decode($json_string);
		} elseif ( $this->api_return_format == 'json' ) {
			$return = $json_string;
		}

		# Check if we need to reformat it.
		if (isset($args['reformat'])) {
			$reformat = $args['reformat'];
		} else {
			$reformat = false;
		}

		// Check if we skip adding this request to the cache
		if ( !isset( $args['skip_add_to_cache']) ) {
			// Add this new request to the request cache and return it
			return $this->add_to_request_cache($api_request_hash, $return, $reformat);
		} else {
			if ($reformat) {
				return $this->format_request($return);
			} else {
				return $return;
			}
		}


	}

	private function __create_ch($api_request) {

		// This function creates a cURL handler for a given URL. In our case, this includes entire API request, with endpoint and parameters

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $api_request);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		if ( $this->api_request_method != 'GET' AND $this->curl_postfields ) {
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $this->curl_postfields );
		}

		// Set the cURL request method - works for all of them

		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $this->api_request_method );

		// Below line is for dev purposes - remove before release
		// curl_setopt($ch, CURLOPT_HEADER, 1);

		$headers = array(
			'Authorization: Bearer ' . $this->access_token,
			'User-Agent: Patreon-PHP, version 1.0.2, platform ' . php_uname('s') . '-' . php_uname( 'r' ),
		);

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		return $ch;

	}

	public function add_to_request_cache( $api_request_hash, $result, $reformat = false ) {

		// This function manages the array that is used as the cache for API requests. What it does is to accept a md5 hash of entire query string (GET, with url, endpoint and options and all) and then add it to the request cache array

		// If the cache array is larger than 50, snip the first item. This may be increased in future

		if ( !empty($this->request_cache) && (count( $this->request_cache ) > 50)  ) {
			array_shift( $this->request_cache );
		}

		// Add the new request and return it
		$this->request_cache[$api_request_hash] = $result;
		if ($reformat) {
			return $this->format_request($result);
		} else {
			return $result;
		}

	}

	public function format_request( $request ) {
		$type = $request['data']['type'];
		$return = [];
		if ($type == 'user') {
			$return['user']['id'] = $request['data']['id'];
			foreach($request['data']['attributes'] as $key=>$data) {
				$return['user'][$key] = $data;
			}


		}

		return $return;
	}


}

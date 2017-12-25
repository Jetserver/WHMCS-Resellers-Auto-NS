<?php
/*
*
* Reseller Name Servers
* Created By Idan Ben-Ezra
*
* Copyrights @ Jetserver Web Hosting
* www.jetserver.net
*
* Hook version 1.0.1
*
**/

if (!defined("WHMCS"))
	die("This file cannot be accessed directly");

function hook_ResellerNameServers_fields($vars)
{
	if($vars['filename'] == 'configproducts')
	{
		// create table for the first time.
		$sql = "CREATE TABLE IF NOT EXISTS `mod_resellernameservers` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`pid` int(11) NOT NULL DEFAULT '0',
			`active` int(11) NOT NULL DEFAULT '0',
			`prefix` varchar(255) NOT NULL,
			`department` int(11) NOT NULL DEFAULT '0',
			PRIMARY KEY (`id`)
			) ENGINE=MyISAM";
		mysql_query($sql);

		$product_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

		$sql = "SELECT *
			FROM tblproducts
			WHERE id = '{$product_id}'";
		$result = mysql_query($sql);
		$product_details = mysql_fetch_assoc($result);

		if($product_details && $product_details['servertype'] == 'cpanel')
		{
			$sql = "SELECT *
				FROM mod_resellernameservers
				WHERE pid = '{$product_id}'";
			$result = mysql_query($sql);
			$nameservers_details = mysql_fetch_assoc($result);

			$active = isset($nameservers_details['active']) ? intval($nameservers_details['active']) : 0;
			$prefix = isset($nameservers_details['prefix']) ? $nameservers_details['prefix'] : '';
			$department = isset($nameservers_details['department']) ? intval($nameservers_details['department']) : 0;

			$options = array();

			$sql = "SELECT *
				FROM tblticketdepartments";
			$result = mysql_query($sql);

			while($department_details = mysql_fetch_assoc($result))
			{
				$options .= "<option value=\"{$department_details['id']}\"" . ($department_details['id'] == $department ? " selected=\"selected\"" : '') . ">{$department_details['name']}</option>";
			}
			mysql_free_result($result);

			return "<script type='text/javascript'>$(document).ready(function() { var contentBox = $('#tab3');var delegationTable = $('<table />').addClass('form').css({ marginTop: '15px' }).attr({width: '100%',cellspacing: '2',cellpadding: '3',border: '0'});delegationTable.append('<tr><td class=\"fieldlabel\">Account Nameservers A Record</td><td class=\"fieldarea\"><input type=\"checkbox\" value=\"1\" " . ($active ? "checked=\"checked\" " : '') . "name=\"rnsactive\" /> Create nameservers A records for new accounts</td><td class=\"fieldlabel\">Account Nameservers Prefix</td><td class=\"fieldarea\"><input type=\"text\" value=\"{$prefix}\" size=\"25\" name=\"rnsprefix\" /></td></tr><tr><td class=\"fieldlabel\">Department to Open Ticket on Error</td><td class=\"fieldarea\"><select name=\"rnsdepartment\"><option value=\"0\">Don\'t Open Ticket</option>{$options}</select></td><td class=\"fieldlabel\"></td><td class=\"fieldarea\"></td></tr>');contentBox.children('table:eq(1)').after(delegationTable);});</script>";
		}
	}
}

function hook_ResellerNameServers_save($vars)
{
	$product_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

	if($vars['servertype'] == 'cpanel')
	{
		$active = isset($_REQUEST['rnsactive']) ? intval($_REQUEST['rnsactive']) : 0;
		$prefix = isset($_REQUEST['rnsprefix']) ? $_REQUEST['rnsprefix'] : '';
		$department = isset($_REQUEST['rnsdepartment']) ? intval($_REQUEST['rnsdepartment']) : 0;

		if($active)
		{
			$sql = "SELECT *
				FROM mod_resellernameservers
				WHERE pid = '{$product_id}'";
			$result = mysql_query($sql);
			$nameservers_details = mysql_fetch_assoc($result);

			if($nameservers_details)
			{
				$sql = "UPDATE mod_resellernameservers
					SET active = '{$active}', prefix = '{$prefix}', department = '{$department}'
					WHERE id = '{$nameservers_details['id']}'";
				mysql_query($sql);
			}
			else
			{
				$sql = "INSERT INTO mod_resellernameservers (`pid`,`active`,`prefix`,`department`) VALUES
					('{$product_id}','{$active}','{$prefix}','{$department}')";
				mysql_query($sql);
			}
		}
		else
		{
			$sql = "DELETE
				FROM mod_resellernameservers
				WHERE pid = '{$product_id}'";
			mysql_query($sql);
		}
	}
	else
	{
		$sql = "DELETE
			FROM mod_resellernameservers
			WHERE pid = '{$product_id}'";
		mysql_query($sql);
	}
}

function hook_ResellerNameServers_create($vars)
{
	global $CONFIG;

	$product_id = $vars['params']['pid'];

	$sql = "SELECT *
		FROM mod_resellernameservers
		WHERE pid = '{$product_id}'";
	$result = mysql_query($sql);
	$nameservers_details = mysql_fetch_assoc($result);

	if($nameservers_details)
	{
		$active = isset($nameservers_details['active']) ? intval($nameservers_details['active']) : 0;
		$prefix = isset($nameservers_details['prefix']) ? $nameservers_details['prefix'] : '';
		$department = isset($nameservers_details['department']) ? intval($nameservers_details['department']) : 0;

		$sql = "SELECT *
			FROM tblservers
			WHERE id = '{$vars['params']['serverid']}'";
		$result = mysql_query($sql);
		$server_details = mysql_fetch_assoc($result);

		if($server_details && $server_details['username'] && ($server_details['accesshash'] || $server_details['password']))
		{
			$response = hook_ResellerNameServers_request($server_details, "scripts/editsets");

			logModuleCall('resellernameservers', 'editsets', array(), $response);

			if($response['success'])
			{
				preg_match_all("/name=\"NS([0-9]?)\"\s+value=\"([A-Za-z0-9\-\.\_]+)\"/", $response['output'], $fields, PREG_SET_ORDER);

				if(sizeof($fields))
				{
					$field = false;
					$nameservers = array();
					$num = 0;

					foreach($fields as $nameserver)
					{
						$ip = gethostbyname($nameserver[2]);

						if(filter_var($ip, FILTER_VALIDATE_IP) === false)
						{
							$field = true;
							break;
						}

						$num = intval($nameserver[1]) > 0 ? intval($nameserver[1]) : ($num+1);

						$nameservers[] = array(
							'ns' 	=> $nameserver[2],
							'ip' 	=> $ip,
							'num' 	=> $num,
						);
					}

					if(!$field)
					{
						foreach($nameservers as $nameserver_details)
						{
							$record_data = array(
								'domain'	=> $vars['params']['domain'],
								'name'		=> "{$prefix}{$nameserver_details['num']}.{$vars['params']['domain']}.",
								'class'		=> 'IN',
								'ttl'		=> '14400',
								'type'		=> 'A',
								'address'	=> $nameserver_details['ip'],
								'api.version'	=> 1,
							);

							$response = hook_ResellerNameServers_request($server_details, "json-api/addzonerecord", $record_data);

							logModuleCall('resellernameservers', 'addzonerecord', $record_data, $response);

							if(!$response['success'] && $department)
							{
								localAPI('openticket', array(
									'clientid'	=> 0,
									'name'		=> 'Reseller Nameservers Hook',
									'email'		=> $CONFIG['Email'],
				 					'deptid' 	=> $department,
									'subject' 	=> "Failed to add A records to the reseller account",
									'message' 	=> "We tried to add A records for the account '{$vars['params']['username']}' unseccessfully.\nUnable to create the A record ({$prefix}{$nameserver_details['num']}.{$vars['params']['domain']} points to {$nameserver_details['ip']}) Response Error Message: {$response['message']}",
									'priority' 	=> 'High',
								));
							}
							elseif($response['success'])
							{
								logActivity("Reseller Nameservers - The A Record {$prefix}{$nameserver_details['num']}.{$vars['params']['domain']} that points to {$nameserver_details['ip']} created successfully - Service ID: {$vars['params']['serviceid']}");
							}
						}
					}
					elseif($department)
					{
						// unable to get nameservers IPs
						localAPI('openticket', array(
							'clientid'	=> 0,
							'name'		=> 'Reseller Nameservers Hook',
							'email'		=> $CONFIG['Email'],
		 					'deptid' 	=> $department,
							'subject' 	=> "Failed to add A records to the reseller account",
							'message' 	=> "We tried to add A records for the account '{$vars['params']['username']}' unseccessfully.\nUnable to retrieve Nameservers IPs found on {$server_details['name']} Basic cPanel & WHM Setup",
							'priority' 	=> 'High',
						));
					}
				}
				elseif($department)
				{
					// no name servers found
					localAPI('openticket', array(
						'clientid'	=> 0,
						'name'		=> 'Reseller Nameservers Hook',
						'email'		=> $CONFIG['Email'],
		 				'deptid' 	=> $department,
						'subject' 	=> "Failed to add A records to the reseller account",
						'message' 	=> "We tried to add A records for the account '{$vars['params']['username']}' unseccessfully.\nNo Nameservers found on {$server_details['name']} Basic cPanel & WHM Setup",
						'priority' 	=> 'High',
					));
				}
			}
		}
		elseif($department)
		{
			localAPI('openticket', array(
				'clientid'	=> 0,
				'name'		=> 'Reseller Nameservers Hook',
				'email'		=> $CONFIG['Email'],
 				'deptid' 	=> $department,
				'subject' 	=> "Failed to add A records to the reseller account",
				'message' 	=> "We tried to add A records for the account '{$vars['params']['username']}' unseccessfully.\n" . ($server_details ? "We unable to find the server #{$vars['params']['serverid']} in the system database" : (!$server_details['username'] ? "Username and " : '') . "Password or Accesshash is missing for the server {$server_details['name']}.\nUnable to connect to the server without those details."),
				'priority' 	=> 'High',
			));
		}
	}
}

function hook_ResellerNameServers_request($server_details, $url, $params = '')
{
	$output = array('success' => true, 'message' => '', 'output' => '');

	if($server_details['accesshash'])
	{
		$authorization = "Authorization: WHM {$server_details['username']}:" . preg_replace("'(\r|\n)'", "", $server_details['accesshash']);
	}
	else
	{
		$authorization = "Authorization: Basic " . base64_encode("{$server_details['username']}:{$server_details['password']}");
	}

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, "https://{$server_details['hostname']}:2087/{$url}");
	if($params) curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array($authorization));

	$output['output'] = curl_exec($ch);

	curl_close($ch);

	return $output;
}

add_hook('AdminAreaHeadOutput', 	1, 'hook_ResellerNameServers_fields');
add_hook('ProductEdit', 		1, 'hook_ResellerNameServers_save');
add_hook('AfterModuleCreate', 		1, 'hook_ResellerNameServers_create');

?>

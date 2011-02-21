<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
/**
 * File containing CUser class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Users
 */
class CUser extends CZBXAPI{
/**
 * Get Users data
 *
 * @param array $options
 * @param array $options['nodeids'] filter by Node IDs
 * @param array $options['usrgrpids'] filter by UserGroup IDs
 * @param array $options['userids'] filter by User IDs
 * @param boolean $options['type'] filter by User type [ USER_TYPE_ZABBIX_USER: 1, USER_TYPE_ZABBIX_ADMIN: 2, USER_TYPE_SUPER_ADMIN: 3 ]
 * @param boolean $options['select_usrgrps'] extend with UserGroups data for each User
 * @param boolean $options['get_access'] extend with access data for each User
 * @param boolean $options['count'] output only count of objects in result. ( result returned in property 'rowscount' )
 * @param string $options['pattern'] filter by Host name containing only give pattern
 * @param int $options['limit'] output will be limited to given number
 * @param string $options['sortfield'] output will be sorted by given property [ 'userid', 'alias' ]
 * @param string $options['sortorder'] output will be sorted in given order [ 'ASC', 'DESC' ]
 * @return array
 */
	public function get($options=array()){
		$result = array();
		$user_type = self::$userData['type'];
		$userid = self::$userData['userid'];

		$sort_columns = array('userid', 'alias'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params

		$sql_parts = array(
			'select' => array('users' => 'u.userid'),
			'from' => array('users' => 'users u'),
			'where' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'					=> null,
			'usrgrpids'					=> null,
			'userids'					=> null,
			'mediaids'					=> null,
			'mediatypeids'				=> null,

// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'			=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,

// OutPut
			'output'					=> API_OUTPUT_REFER,
			'editable'					=> null,
			'select_usrgrps'			=> null,
			'select_medias'				=> null,
			'select_mediatypes'			=> null,
			'get_access'				=> null,
			'countOutput'				=> null,
			'preservekeys'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);

		$options = zbx_array_merge($def_options, $options);

		if(is_array($options['output'])){
			unset($sql_parts['select']['users']);

			$dbTable = DB::getSchema('users');
			$sql_parts['select']['userid'] = ' u.userid';
			foreach($options['output'] as $key => $field){
				if(isset($dbTable['fields'][$field]))
					$sql_parts['select'][$field] = ' u.'.$field;
			}

			$options['output'] = API_OUTPUT_CUSTOM;
		}

// PERMISSION CHECK
		if(USER_TYPE_SUPER_ADMIN == $user_type){

		}
		else if(is_null($options['editable']) && (self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN)){
			$sql_parts['from']['users_groups'] = 'users_groups ug';
			$sql_parts['where']['uug'] = 'u.userid=ug.userid';
			$sql_parts['where'][] = 'ug.usrgrpid IN ('.
				' SELECT uug.usrgrpid'.
				' FROM users_groups uug'.
				' WHERE uug.userid='.self::$userData['userid'].
				' )';
		}
		else if(!is_null($options['editable']) || (self::$userData['type']!=USER_TYPE_SUPER_ADMIN)){
			$options['userids'] = self::$userData['userid'];
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// userids
		if(!is_null($options['userids'])){
			zbx_value2array($options['userids']);
			$sql_parts['where'][] = DBcondition('u.userid', $options['userids']);
		}

// usrgrpids
		if(!is_null($options['usrgrpids'])){
			zbx_value2array($options['usrgrpids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['usrgrpid'] = 'ug.usrgrpid';
			}
			$sql_parts['from']['users_groups'] = 'users_groups ug';
			$sql_parts['where'][] = DBcondition('ug.usrgrpid', $options['usrgrpids']);
			$sql_parts['where']['uug'] = 'u.userid=ug.userid';
		}

// mediaids
		if(!is_null($options['mediaids'])){
			zbx_value2array($options['mediaids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['mediaid'] = 'm.mediaid';
			}
			$sql_parts['from']['media'] = 'media m';
			$sql_parts['where'][] = DBcondition('m.mediaid', $options['mediaids']);
			$sql_parts['where']['mu'] = 'm.userid=u.userid';
		}

// mediatypeids
		if(!is_null($options['mediatypeids'])){
			zbx_value2array($options['mediatypeids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['mediatypeid'] = 'm.mediatypeid';
			}
			$sql_parts['from']['media'] = 'media m';
			$sql_parts['where'][] = DBcondition('m.mediatypeid', $options['mediatypeids']);
			$sql_parts['where']['mu'] = 'm.userid=u.userid';
		}

// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['users'] = 'u.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(DISTINCT u.userid) as rowscount');
		}

// filter
		if(is_array($options['filter'])){
			if($options['filter']['passwd']){
				self::exception(ZBX_API_ERROR_PARAMETERS, _('It is not possible to filter by user password'));
			}
			zbx_db_filter('users u', $options, $sql_parts);
		}

// search
		if(is_array($options['search'])){
			if($options['search']['passwd']){
				self::exception(ZBX_API_ERROR_PARAMETERS, _('It is not possible to search by user password'));
			}
			zbx_db_search('users u', $options, $sql_parts);
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 'u.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('u.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('u.*', $sql_parts['select'])){
				$sql_parts['select'][] = 'u.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//-------
		$userids = array();

		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_order = '';
		if(!empty($sql_parts['select']))	$sql_select.= implode(',',$sql_parts['select']);
		if(!empty($sql_parts['from']))		$sql_from.= implode(',',$sql_parts['from']);
		if(!empty($sql_parts['where']))		$sql_where.= ' AND '.implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.'
				FROM '.$sql_from.'
				WHERE '.DBin_node('u.userid', $nodeids).
				$sql_where.
				$sql_order;
//SDI($sql);
		$res = DBselect($sql, $sql_limit);
		while($user = DBfetch($res)){
			unset($user['passwd']);
			if(!is_null($options['countOutput'])){
				$result = $user['rowscount'];
			}
			else{
				$userids[$user['userid']] = $user['userid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$user['userid']] = array('userid' => $user['userid']);
				}
				else{
					if(!isset($result[$user['userid']])) $result[$user['userid']]= array();

					if($options['select_usrgrps'] && !isset($result[$user['userid']]['usrgrps'])){
						$result[$user['userid']]['usrgrps'] = array();
					}

// usrgrpids
					if(isset($user['usrgrpid']) && is_null($options['select_usrgrps'])){
						if(!isset($result[$user['userid']]['usrgrps']))
							$result[$user['userid']]['usrgrps'] = array();

						$result[$user['userid']]['usrgrps'][] = array('usrgrpid' => $user['usrgrpid']);
						unset($user['usrgrpid']);
					}

// mediaids
					if(isset($user['mediaid']) && is_null($options['select_medias'])){
						if(!isset($result[$user['userid']]['medias']))
							$result[$user['userid']]['medias'] = array();

						$result[$user['userid']]['medias'][] = array('mediaid' => $user['mediaid']);
						unset($user['mediaid']);
					}

// mediatypeids
					if(isset($user['mediatypeid']) && is_null($options['select_mediatypes'])){
						if(!isset($result[$user['userid']]['mediatypes']))
							$result[$user['userid']]['mediatypes'] = array();

						$result[$user['userid']]['mediatypes'][] = array('mediatypeid' => $user['mediatypeid']);
						unset($user['mediatypeid']);
					}
					$result[$user['userid']] += $user;
				}
			}
		}

Copt::memoryPick();
		if(!is_null($options['countOutput'])){
			return $result;
		}

// Adding Objects
		if(!is_null($options['get_access'])){
			foreach($result as $userid => $user){
				$result[$userid] += array('gui_access' => 0, 'debug_mode' => 0, 'users_status' => 0);
			}

			$sql = 'SELECT ug.userid,  MAX(g.gui_access) as gui_access,
						MAX(g.debug_mode) as debug_mode, MAX(g.users_status) as users_status'.
					' FROM usrgrp g, users_groups ug '.
					' WHERE '.DBcondition('ug.userid', $userids).
						' AND g.usrgrpid=ug.usrgrpid '.
					' GROUP BY ug.userid';
			$access = DBselect($sql);
			while($useracc = DBfetch($access)){
				$result[$useracc['userid']] = zbx_array_merge($result[$useracc['userid']], $useracc);
			}
		}

// Adding usergroups
		if(!is_null($options['select_usrgrps']) && str_in_array($options['select_usrgrps'], $subselects_allowed_outputs)){
			$obj_params = array(
				'output' => $options['select_usrgrps'],
				'userids' => $userids,
				'preservekeys' => 1
			);
			$usrgrps = API::UserGroup()->get($obj_params);
			foreach($usrgrps as $usrgrpid => $usrgrp){
				$uusers = $usrgrp['users'];
				unset($usrgrp['users']);
				foreach($uusers as $num => $user){
					$result[$user['userid']]['usrgrps'][] = $usrgrp;
				}
			}
		}

// TODO:
// Adding medias
		if(!is_null($options['select_medias']) && str_in_array($options['select_medias'], $subselects_allowed_outputs)){
		}
// Adding mediatypes
		if(!is_null($options['select_mediatypes']) && str_in_array($options['select_mediatypes'], $subselects_allowed_outputs)){
		}

// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

/**
 * Add Users
 *
 * @param array $users multidimensional array with Users data
 * @param string $users['name']
 * @param string $users['surname']
 * @param array $users['alias']
 * @param string $users['passwd']
 * @param string $users['url']
 * @param int $users['autologin']
 * @param int $users['autologout']
 * @param string $users['lang']
 * @param string $users['theme']
 * @param int $users['refresh']
 * @param int $users['rows_per_page']
 * @param int $users['type']
 * @param array $users['user_medias']
 * @param string $users['user_medias']['mediatypeid']
 * @param string $users['user_medias']['address']
 * @param int $users['user_medias']['severity']
 * @param int $users['user_medias']['active']
 * @param string $users['user_medias']['period']
 * @return array|boolean
 */
	public function create($users){


			if(USER_TYPE_SUPER_ADMIN != self::$userData['type']){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSIONS);
			}

			$users = zbx_toArray($users);
			$userids = array();

			foreach($users as $unum => $user){

				$user_db_fields = array(
					'name' => 'ZABBIX',
					'surname' => 'USER',
					'alias' => null,
					'passwd' => 'zabbix',
					'url' => '',
					'autologin' => 0,
					'autologout' => 900,
					'lang' => 'en_gb',
					'theme' => 'default.css',
					'refresh' => 30,
					'rows_per_page' => 50,
					'type' => USER_TYPE_ZABBIX_USER,
					'user_medias' => array(),
				);
				if(!check_db_fields($user_db_fields, $user)){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_WRONG_FIELD_FOR_USER);
				}

				$user_exist = $this->get(array('filter' => array('alias' => $user['alias'])));
				if(!empty($user_exist)){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_USER_EXISTS_FIRST_PART);
				}

				$userid = get_dbid('users', 'userid');
				$sql = 'INSERT INTO users (userid, name, surname, alias, passwd, url, autologin, autologout, lang, theme,
					refresh, rows_per_page, type) '.
					' VALUES ('.
						$userid.','.
						zbx_dbstr($user['name']).','.
						zbx_dbstr($user['surname']).','.
						zbx_dbstr($user['alias']).','.
						zbx_dbstr(md5($user['passwd'])).','.
						zbx_dbstr($user['url']).','.
						$user['autologin'].','.
						$user['autologout'].','.
						zbx_dbstr($user['lang']).','.
						zbx_dbstr($user['theme']).','.
						$user['refresh'].','.
						$user['rows_per_page'].','.
						$user['type'].
					')';
				if(!DBexecute($sql))
					self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');

				$usrgrps = zbx_objectValues($user['usrgrps'], 'usrgrpid');
				foreach($usrgrps as $groupid){
					$users_groups_id = get_dbid("users_groups","id");
					$sql = 'INSERT INTO users_groups (id,usrgrpid,userid)'.
						'values('.$users_groups_id.','.$groupid.','.$userid.')';
					if(!DBexecute($sql))
						self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
				}

				foreach($user['user_medias'] as $media_data){
					$mediaid = get_dbid('media', 'mediaid');
					$sql = 'INSERT INTO media (mediaid,userid,mediatypeid,sendto,active,severity,period)'.
						' VALUES ('.$mediaid.','.$userid.','.$media_data['mediatypeid'].','.
						zbx_dbstr($media_data['sendto']).','.$media_data['active'].','.$media_data['severity'].','.
						zbx_dbstr($media_data['period']).')';
					if(!DBexecute($sql))
						self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
				}

				$userids[] = $userid;
			}

			return array('userids' => $userids);
	}

/**
 * Update Users
 *
 * @param array $users multidimensional array with Users data
 * @param string $users['userid']
 * @param string $users['name']
 * @param string $users['surname']
 * @param array $users['alias']
 * @param string $users['passwd']
 * @param string $users['url']
 * @param int $users['autologin']
 * @param int $users['autologout']
 * @param string $users['lang']
 * @param string $users['theme']
 * @param int $users['refresh']
 * @param int $users['rows_per_page']
 * @param int $users['type']
 * @param array $users['user_medias']
 * @param string $users['user_medias']['mediatypeid']
 * @param string $users['user_medias']['address']
 * @param int $users['user_medias']['severity']
 * @param int $users['user_medias']['active']
 * @param string $users['user_medias']['period']
 * @return boolean
 */
	public function update($users){

		$self = false;

			if(USER_TYPE_SUPER_ADMIN != self::$userData['type']){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_CUSER_ERROR_ONLY_SUPER_ADMIN_CAN_UPDATE_USERS);
			}

			$users = zbx_toArray($users);

			$options = array(
				'userids' => zbx_objectValues($users, 'userid'),
			'output' => API_OUTPUT_EXTEND,
				'preservekeys' => 1
			);
			$upd_users = $this->get($options);
			foreach($users as $gnum => $user){
				//add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER, 'User ['.$user['alias'].']');
			}

			if(bccomp(self::$userData['userid'], $user['userid']) == 0){
				$self = true;
			}

			foreach($users as $unum => $user){
				$user_db_fields = $upd_users[$user['userid']];

	// check if we change guest user
				if(($user_db_fields['alias'] == ZBX_GUEST_USER) && isset($user['alias']) && ($user['alias'] != ZBX_GUEST_USER)){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_CANT_RENAME_GUEST_USER);
				}


	// unset if not changed passwd
				if(isset($user['passwd']) && !is_null($user['passwd'])){
					$user['passwd'] = md5($user['passwd']);
					$user_db_fields['passwd'] = '';
				}
				else{
					unset($user['passwd']);
				}
	//---------

				if(!check_db_fields($user_db_fields, $user)){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_WRONG_FIELD_FOR_USER);
				}

	// copy from frontend {
				$sql = 'SELECT userid '.
						' FROM users '.
						' WHERE alias='.zbx_dbstr($user['alias']).
							' AND '.DBin_node('userid', id2nodeid($user['userid']));
				$db_user = DBfetch(DBselect($sql));
				if($db_user && ($db_user['userid'] != $user['userid'])){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_USER_EXISTS_FIRST_PART.' '.$user['alias'].' '.S_CUSER_ERROR_USER_EXISTS_SECOND_PART);
				}

				$result = DB::update('users', array(array('values'=>$user,'where'=>array('userid='.$user['userid']))));
				if(!$result)
					self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');

				// if(isset($user['usrgrps']) && !is_null($user['usrgrps'])){
					// $user_groups = API::HostGroup()->get(array('userids' => $user['userid']));
					// $user_groupids = zbx_objectValues($user_groups, 'usrgrpid');
					// $new_groupids = zbx_objectValues($user['usrgrps'], 'usrgrpid');

					// $groups_to_add = array_diff($new_groupids, $user_groupids);

					// if(!empty($groups_to_add)){
						// $result &= $this->massAdd(array('users' => $user, 'usrgrps' => $groups_to_add));
					// }

					// $groups_to_del = array_diff($user_groupids, $new_groupids);
					// if(!empty($groups_to_del)){
						// $result &= $this->massRemove(array('users' => $user, 'usrgrps' => $groups_to_del));
					// }
				// }



				if(isset($user['usrgrps']) && !is_null($user['usrgrps'])){

					// list with group id's where user must be after update
					$user_must_be_in_groups = zbx_objectValues($user['usrgrps'], 'usrgrpid');

					// deleting all relations with groups, but not touching those, where user still must be after update
					$sql = 'DELETE FROM users_groups WHERE userid='.$user['userid'].' AND '.DBcondition('usrgrpid', $user_must_be_in_groups, true);  // true - NOT IN
					DBexecute($sql);

					// getting the list of groups user is currently in
					$db_groups_user_is_in = DBSelect('SELECT usrgrpid FROM users_groups WHERE userid='.$user['userid']);
					$groups_user_is_in = array();
					while($grp = DBfetch($db_groups_user_is_in)){
						$groups_user_is_in[] = $grp['usrgrpid'];
					}

					$options = array(
						'usrgrpids' => $user_must_be_in_groups,
						'output' => API_OUTPUT_EXTEND,
						'preservekeys' => 1
					);
					$usrgrps = API::UserGroup()->get($options);

					foreach($usrgrps as $groupid => $group){
						if(($group['gui_access'] == GROUP_GUI_ACCESS_DISABLED) && $self){
							self::exception(ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_USER_UNABLE_RESTRICT_SELF_GUI_ACCESS_PART1);
						}

						if(($group['users_status'] == GROUP_STATUS_DISABLED) && $self){
							self::exception(ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_USER_CANT_DISABLE_SELF_PART1);
						}

						// if user is not already in a given group
						if (!in_array($groupid, $groups_user_is_in)){
							$users_groups_id = get_dbid('users_groups', 'id');
							$sql = 'INSERT INTO users_groups (id, usrgrpid, userid)'.
									' VALUES ('.$users_groups_id.','.$groupid.','.$user['userid'].')';
							if(!DBexecute($sql))
								self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
						}
					}
				}
	/*
				if($result && !is_null($user['user_medias'])){
					$result = DBexecute('DELETE FROM media WHERE userid='.$userid);
					foreach($user['user_medias'] as $media_data){
						if(!$result) break;
						$mediaid = get_dbid('media', 'mediaid');
						$result = DBexecute('INSERT INTO media (mediaid, userid, mediatypeid, sendto, active, severity, period)'.
							' VALUES ('.$mediaid.','.$userid.','.$media_data['mediatypeid'].','.
								zbx_dbstr($media_data['sendto']).','.$media_data['active'].','.$media_data['severity'].','.
								zbx_dbstr($media_data['period']).')');
					}
				}
	//*/
			}

			return array('userids' => $user['userid']);
	}

	public function updateProfile($user){


			$options = array(
				'nodeids' => id2nodeid(self::$userData['userid']),
				'userids' => self::$userData['userid'],
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => 1
			);
			$upd_users = $this->get($options);
			$upd_user = reset($upd_users);

			$user_db_fields = $upd_user;

// unset if not changed passwd
			if(isset($user['passwd']) && !is_null($user['passwd'])){
				$user['passwd'] = md5($user['passwd']);
				$user_db_fields['passwd'] = '';
			}
			else{
				unset($user['passwd']);
			}
//---------

			if(!check_db_fields($user_db_fields, $user)){
				self::exception(ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_WRONG_FIELD_FOR_USER);
			}

			$result = DB::update('users', array(array('values'=>$user,'where'=>array('userid='.$user['userid']))));
			if(!$result)
				self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');

			return $user;
	}
/**
 * Delete Users
 *
 * @param array $users
 * @param array $users[0,...]['userids']
 * @return boolean
 */
	public function delete($users){


		$users = zbx_toArray($users);
		$userids = zbx_objectValues($users, 'userid');

			if(USER_TYPE_SUPER_ADMIN != self::$userData['type']){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_CUSER_ERROR_ONLY_SUPER_ADMIN_CAN_DELETE_USERS);
			}

			$options = array(
				'userids' => $userids,
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => 1
			);
			$del_users = $this->get($options);
			foreach($del_users as $gnum => $user){
				if(bccomp(self::$userData['userid'], $user['userid']) == 0){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_USER_CANNOT_DELETE_ITSELF);
				}

				if($del_users[$user['userid']]['alias'] == ZBX_GUEST_USER){
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Can not delete %1$s internal user " %2$s ", try disabling that user.', S_ZABBIX, ZBX_GUEST_USER));
				}
			}

// delete action operation msg
			$operationids = array();
			$sql = 'SELECT DISTINCT om.operationid '.
					' FROM opmessage_usr om '.
					' WHERE '.DBcondition('om.userid', $userids);
			$dbOperations = DBselect($sql);
			while($dbOperation = DBfetch($dbOperations))
				$operationids[$dbOperation['operationid']] = $dbOperation['operationid'];

			DB::delete('opmessage_usr', array('userid'=>$userids));

// delete empty operations
			$delOperationids = array();
			$sql = 'SELECT DISTINCT o.operationid '.
					' FROM operations o '.
					' WHERE '.DBcondition('o.operationid', $operationids).
						' AND NOT EXISTS(SELECT om.opmessage_usrid FROM opmessage_usr om WHERE om.operationid=o.operationid)';
			$dbOperations = DBselect($sql);
			while($dbOperation = DBfetch($dbOperations))
				$delOperationids[$dbOperation['operationid']] = $dbOperation['operationid'];

			DB::delete('operations', array('operationid'=>$delOperationids));
			DB::delete('media', array('userid'=>$userids));
			DB::delete('profiles', array('userid'=>$userids));
			DB::delete('users_groups', array('userid'=>$userids));
			DB::delete('users', array('userid'=>$userids));

			return array('userids' => $userids);
	}

/**
 * Add Medias for User
 *
 * @param array $media_data
 * @param string $media_data['userid']
 * @param string $media_data['medias']['mediatypeid']
 * @param string $media_data['medias']['address']
 * @param int $media_data['medias']['severity']
 * @param int $media_data['medias']['active']
 * @param string $media_data['medias']['period']
 * @return boolean
 */
	public function addMedia($media_data){


			$medias = zbx_toArray($media_data['medias']);
			$users = zbx_toArray($media_data['users']);
			$mediaids = array();

		$userids = array();

			if(self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN){
				self::exception(ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_ONLY_ADMIN_CAN_ADD_USER_MEDIAS);
			}

			foreach($users as $unum => $user){
			$userids[] = $user['userid'];

				foreach($medias as $mnum => $media){
					if(!validate_period($media['period'])){
						self::exception(ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_INCORRECT_TIME_PERIOD);
					}

					$mediaid = get_dbid('media','mediaid');

					$sql='INSERT INTO media (mediaid,userid,mediatypeid,sendto,active,severity,period) '.
							' VALUES ('.$mediaid.','.$user['userid'].','.$media['mediatypeid'].','.
										zbx_dbstr($media['sendto']).','.$media['active'].','.$media['severity'].','.
										zbx_dbstr($media['period']).')';
					if(!DBexecute($sql))
						self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
					$mediaids[] = $mediaid;
				}
			}

			return array('mediaids' => $mediaids);
	}

/**
 * Delete User Medias
 *
 * @param array $mediaids
 * @return boolean
 */
	public function deleteMedia($mediaids){


			$mediaids = zbx_toArray($mediaids);

			if(self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN){
				self::exception(ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_ONLY_ADMIN_CAN_REMOVE_USER_MEDIAS);
			}

			$sql = 'DELETE FROM media WHERE '.DBcondition('mediaid', $mediaids);
			if(!DBexecute($sql))
				self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');

			return array('mediaids'=>$mediaids);
	}

/**
 * Update Medias for User
 *
 * @param array $media_data
 * @param array $media_data['users']
 * @param array $media_data['users']['userid']
 * @param array $media_data['medias']
 * @param string $media_data['medias']['mediatypeid']
 * @param string $media_data['medias']['sendto']
 * @param int $media_data['medias']['severity']
 * @param int $media_data['medias']['active']
 * @param string $media_data['medias']['period']
 * @return boolean
 */
	public function updateMedia($media_data){



		$new_medias = zbx_toArray($media_data['medias']);
		$users = zbx_toArray($media_data['users']);

			if(self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_CUSER_ERROR_ONLY_ADMIN_CAN_CHANGE_USER_MEDIAS);
			}

			$upd_medias = array();
			$del_medias = array();

			$userids = zbx_objectValues($users, 'userid');
			$sql = 'SELECT m.mediaid '.
					' FROM media m '.
					' WHERE '.DBcondition('userid', $userids);
			$result = DBselect($sql);
			while($media = DBfetch($result)){
				$del_medias[$media['mediaid']] = $media;
			}

			foreach($new_medias as $mnum => $media){
				if(!isset($media['mediaid'])) continue;

				if(isset($del_medias[$media['mediaid']])){
					$upd_medias[$media['mediaid']] = $new_medias[$mnum];
				}

				unset($new_medias[$mnum]);
				unset($del_medias[$media['mediaid']]);
			}

// DELETE
			if(!empty($del_medias)){
				$mediaids = zbx_objectValues($del_medias, 'mediaid');
				$result = $this->deleteMedia($mediaids);
				if(!$result){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_CANT_DELETE_USER_MEDIAS);
				}
			}

// UPDATE
			foreach($upd_medias as $mnum => $media){
				if(!validate_period($media['period'])){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_WRONG_PERIOD_PART1.' '.$media['period'].' '.S_CUSER_ERROR_WRONG_PERIOD_PART2);
				}

				$sql = 'UPDATE media '.
						' SET mediatypeid='.$media['mediatypeid'].','.
							' sendto='.zbx_dbstr($media['sendto']).','.
							' active='.$media['active'].','.
							' severity='.$media['severity'].','.
							' period='.zbx_dbstr($media['period']).
						' WHERE mediaid='.$media['mediaid'];
				$result = DBexecute($sql);
				if(!$result){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_CANT_UPDATE_USER_MEDIAS);
				}
			}

// CREATE
			if(!empty($new_medias)){
				$result = $this->addMedia(array('users' => $users, 'medias' => $new_medias));
				if(!$result){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_CANT_INSERT_USER_MEDIAS);
				}
			}

			return array('userids'=>$userids);
	}

// ******************************************************************************
//  LOGIN Methods
// ******************************************************************************

	public function ldapLogin($user){
		$cnf = isset($user['cnf']) ? $user['cnf'] : null;

		if(is_null($cnf)){
			$config = select_config();
			foreach($config as $id => $value){
				if(zbx_strpos($id, 'ldap_') !== false){
					$cnf[str_replace('ldap_', '', $id)] = $config[$id];
				}
			}
		}

		if(!function_exists('ldap_connect')){
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Probably php-ldap module is missing'));
		}

		$ldap = new CLdap($cnf);
		$ldap->connect();

		if($ldap->checkPass($user['user'], $user['password']))
			return true;
		else
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Login name or password is incorrect'));
	}

	private function dbLogin($user){
		$sql = 'SELECT u.userid '.
				' FROM users u'.
				' WHERE u.alias='.zbx_dbstr($user['user']).
					' AND u.passwd='.zbx_dbstr(md5($user['password']));
		$login = DBfetch(DBselect($sql));

		if($login)
			return true;
		else
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Login name or password is incorrect'));
	}

	public function logout($sessionid){
		global $ZBX_LOCALNODEID;

		$sql = 'SELECT s.* '.
			' FROM sessions s '.
			' WHERE s.sessionid='.zbx_dbstr($sessionid).
				' AND s.status='.ZBX_SESSION_ACTIVE.
				' AND '.DBin_node('s.userid', $ZBX_LOCALNODEID);

		$session = DBfetch(DBselect($sql));
		if(!$session) self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot logout.'));

		DBexecute('DELETE FROM sessions WHERE status='.ZBX_SESSION_PASSIVE.' AND userid='.zbx_dbstr($session['userid']));
		DBexecute('UPDATE sessions SET status='.ZBX_SESSION_PASSIVE.' WHERE sessionid='.zbx_dbstr($sessionid));

	return true;
	}

/**
 * Login user
 *
 * @param array $user
 * @param array $user['user'] User alias
 * @param array $user['password'] User password
 * @return string session ID
 */
	public function login($user){
		global $ZBX_LOCALNODEID;

		$name = $user['user'];
		$password = md5($user['password']);

		$sql = 'SELECT u.userid, u.attempt_failed, u.attempt_clock, u.attempt_ip'.
				' FROM users u '.
				' WHERE u.alias='.zbx_dbstr($name);
					' AND '.DBin_node('u.userid', $ZBX_LOCALNODEID);

		$userInfo = DBfetch(DBselect($sql));
		if(!$userInfo)
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Login name or password is incorrect'));

		self::$userData['userid'] = $userInfo['userid'];

// check if user is blocked
		if($userInfo['attempt_failed'] >= ZBX_LOGIN_ATTEMPTS){
			if((time() - $userInfo['attempt_clock']) < ZBX_LOGIN_BLOCK)
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Account is blocked for %s seconds', (ZBX_LOGIN_BLOCK - (time() - $userInfo['attempt_clock']))));

			DBexecute('UPDATE users SET attempt_clock='.time().' WHERE alias='.zbx_dbstr($name));
		}

// check system permissions
		if(!check_perm2system($userInfo['userid']))
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions for system access.'));


		$sql = 'SELECT MAX(g.gui_access) as gui_access '.
			' FROM usrgrp g, users_groups ug '.
			' WHERE ug.userid='.$userInfo['userid'].
				' AND g.usrgrpid=ug.usrgrpid ';
		$db_access = DBfetch(DBselect($sql));
		if(!zbx_empty($db_access['gui_access']))
			$guiAccess = $db_access['gui_access'];
		else
			$guiAccess = GROUP_GUI_ACCESS_SYSTEM;

		switch($guiAccess){
			case GROUP_GUI_ACCESS_INTERNAL:
				$auth_type = ZBX_AUTH_INTERNAL;
				break;
			case GROUP_GUI_ACCESS_DISABLED:
			case GROUP_GUI_ACCESS_SYSTEM:
				$config = select_config();
				$auth_type = $config['authentication_type'];
				break;
		}

		try{
			switch($auth_type){
				case ZBX_AUTH_LDAP:
					$this->ldapLogin($user);
					break;
				case ZBX_AUTH_INTERNAL:
					$this->dbLogin($user);
					break;
				case ZBX_AUTH_HTTP:
			}
		}
		catch(APIException $e){
			$ip = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']))
					? $_SERVER['HTTP_X_FORWARDED_FOR']
					: $_SERVER['REMOTE_ADDR'];
			$userInfo['attempt_failed']++;

			$sql = 'UPDATE users '.
					' SET attempt_failed='.$userInfo['attempt_failed'].','.
						' attempt_clock='.time().','.
						' attempt_ip='.zbx_dbstr($ip).
					' WHERE userid='.$userInfo['userid'];
			DBexecute($sql);

			add_audit(AUDIT_ACTION_LOGIN, AUDIT_RESOURCE_USER, _s('Login failed [%s]', $name));
			self::exception(ZBX_API_ERROR_PARAMETERS, $e->getMessage());
		}

// start session
		$sessionid = md5(time().$password.$name.rand(0,10000000));
		DBexecute('INSERT INTO sessions (sessionid,userid,lastaccess,status) VALUES ('.zbx_dbstr($sessionid).','.$userInfo['userid'].','.time().','.ZBX_SESSION_ACTIVE.')');
// --

		add_audit(AUDIT_ACTION_LOGIN, AUDIT_RESOURCE_USER, _s('Correct login [%s]', $name));


		$userData = $this->_getUserData($userInfo['userid']);
		$userData['sessionid'] = $sessionid;
		$userData['gui_access'] = $guiAccess;

		if($userInfo['attempt_failed'])
			DBexecute('UPDATE users SET attempt_failed=0 WHERE userid='.$userInfo['userid']);

		self::$userData = $userData;

	return isset($user['userData']) ? $userData : $userData['sessionid'];
	}

/**
 * Check if session ID is authenticated
 *
 * @param array $sessionid Session ID
 */
	public function checkAuthentication($sessionid){
		global $ZBX_LOCALNODEID;

		$sql = 'SELECT u.userid, u.autologout'.
				' FROM sessions s, users u'.
				' WHERE s.sessionid='.zbx_dbstr($sessionid).
					' AND s.status='.ZBX_SESSION_ACTIVE.
					' AND s.userid=u.userid'.
					' AND ((s.lastaccess+u.autologout>'.time().') OR (u.autologout=0))'.
					' AND '.DBin_node('u.userid', $ZBX_LOCALNODEID);
		$userInfo = DBfetch(DBselect($sql));

		if(!$userInfo)
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Session terminated, re-login, please.'));

		if(!check_perm2system($userInfo['userid']))
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions for system access.'));

		if($userInfo['autologout'] > 0)
			DBexecute('DELETE FROM sessions WHERE userid='.$userInfo['userid'].' AND lastaccess<'.(time() - $userInfo['autologout']));

		DBexecute('UPDATE sessions SET lastaccess='.time().' WHERE userid='.$userInfo['userid'].' AND sessionid='.zbx_dbstr($sessionid));

		$sql = 'SELECT MAX(g.gui_access) as gui_access '.
			' FROM usrgrp g, users_groups ug '.
			' WHERE ug.userid='.$userInfo['userid'].
				' AND g.usrgrpid=ug.usrgrpid ';
		$db_access = DBfetch(DBselect($sql));
		if(!zbx_empty($db_access['gui_access'])){
			$guiAccess = $db_access['gui_access'];
		}
		else{
			$guiAccess = GROUP_GUI_ACCESS_SYSTEM;
		}

		$userData = $this->_getUserData($userInfo['userid']);
		$userData['sessionid'] = $sessionid;
		$userData['gui_access'] = $guiAccess;

		self::$userData = $userData;

		return $userData;
	}

	private function _getUserData($userid){
		global $ZBX_LOCALNODEID;
		global $ZBX_NODES;

		$sql = 'SELECT u.userid, u.alias, u.name, u.surname, u.url, u.autologin, u.autologout, u.lang, u.refresh, u.type,'.
				' u.theme, u.attempt_failed, u.attempt_ip, u.attempt_clock, u.rows_per_page'.
				' FROM users u'.
				' WHERE u.userid='.$userid;
		$userData = DBfetch(DBselect($sql));


		$sql = 'SELECT ug.userid '.
			' FROM usrgrp g, users_groups ug '.
			' WHERE ug.userid = '.$userid.
				' AND g.usrgrpid = ug.usrgrpid '.
				' AND g.debug_mode = '.GROUP_DEBUG_MODE_ENABLED;
		$userData['debug_mode'] = (bool) DBfetch(DBselect($sql));


		$userData['userip'] = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']))
					? $_SERVER['HTTP_X_FORWARDED_FOR']
					: $_SERVER['REMOTE_ADDR'];


		if(isset($ZBX_NODES[$ZBX_LOCALNODEID])){
			$userData['node'] = $ZBX_NODES[$ZBX_LOCALNODEID];
		}
		else{
			$userData['node'] = array();
			$userData['node']['name'] = '- unknown -';
			$userData['node']['nodeid'] = $ZBX_LOCALNODEID;
		}

	return $userData;
	}

	public function isReadable($ids){
		if(!is_array($ids)) return false;
		if(empty($ids)) return true;

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'userids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	public function isWritable($ids){
		if(!is_array($ids)) return false;
		if(empty($ids)) return true;

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'userids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'editable' => true,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}
}

?>

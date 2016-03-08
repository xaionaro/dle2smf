<?php

//phpinfo();

require_once "config.php";

define('DB1',	'ih114110_zoodb');
define('DB2',	'ih114110_zoodb_forum');

$mysql = mysql_connect('localhost', LOGIN, PASSWORD);
mysql_select_db(DB2);

$timeCur = time();

$boards = array();
$ret = mysql_query('select * from `'.DB1.'`.dle_category');// || die(mysql_error());
while($row = mysql_fetch_assoc($ret)) {
	/* From:
		         id: 936
		   parentid: 89
		       posi: 1
		       name: Рогатковые (Cottidae)
		   alt_name: cottidae
		       icon: 
		       skin: 
		      descr: Рогатковые (Cottidae)
		   keywords: Cottidae, Рогатковые
		  news_sort: 
		 news_msort: 
		news_number: 0
		  short_tpl: 
		   full_tpl: 
		  metatitle: 
		   show_sub: 0
		  allow_rss: 1

	To:
			 id_board: 37
			   id_cat: 6
		      child_level: 1
			id_parent: 16
		      board_order: 9
		      id_last_msg: 756
		   id_msg_updated: 756
		    member_groups: -1,0,2,4,5,6,7,8
		       id_profile: 1
			     name: Рукокрылые
		      description: Рукокрылые
		       num_topics: 1
			num_posts: 1
		      count_posts: 0
			 id_theme: 0
		   override_theme: 0
		 unapproved_posts: 0
		unapproved_topics: 0
			 redirect: 

		      id_cat: 9
		   cat_order: 4
			name: Охотничий форум
		can_collapse: 1

	 */

	$boards[$row['parentid']][] = array(
		'oldid'		=> $row['id'],
		'id_cat'	=> 6,
		'board_order' 	=> $row['posi'],
		'member_groups' => '-1,0,2,4,5,6,7,8',
		'id_profile'	=> 1,
		'name'		=> $row['name'],
		'description'	=> $row['descr'],
		'id_theme'	=> 0,
		'override_theme' => 0,
		'unapproved_posts' => '0',
		'unapproved_topics' => '0',
		'redirect'	=> '',
	);

}

//print_r($boards);die();

function insert($table_name, &$row, $exclude_fields = array()) {
	$values = array();
	foreach ($row as $k => $v) {
		if (in_array($k, $exclude_fields))
			continue;
		$values[] = '`'.$k.'` = "'.mysql_real_escape_string($v).'"';
	}
	//echo('INSERT INTO `'.$table_name.'` SET '.join(', ', $values)."\n");
	mysql_query('INSERT INTO `'.$table_name.'` SET '.join(', ', $values));
	return mysql_insert_id();
}

$board_id2newid = array();
//$board_childlevel = array();

function board_insert_recursive(&$board, &$boards, $parentid, $level) {
	global $board_id2newid;

	if (empty($board))
		return;

	foreach ($board as &$b) {
		$b['id_parent']   = $parentid;
		$b['child_level'] = $level;
		$b['id_board']    = insert('smf_boards', $b, array('oldid'));
		$board_id2newid[$b['oldid']] = $b['id_board'];
		//$board_childlevel[$b['id_board']] = $level;

		board_insert_recursive($boards[$b['oldid']], $boards, $b['id_board'], $level+1);
	}
}

board_insert_recursive($boards[0], $boards, 0, 0);

//print_r($board_id2newid);

$ret = mysql_query("select category,title,xfields,CONCAT(id,'-',alt_name,'.html') url from `".DB1."`.dle_post");
while($row = mysql_fetch_assoc($ret)) {
	$words = explode('||', $row['xfields']);
	foreach ($words as $word) {
		$kv = explode('|', $word);
		$row[strtolower($kv[0])] = $kv[1];
	}
	if (empty($row['latin'])) {
		//continue;
		$row['latin'] = $row['title'];
	}

	$boardId = $board_id2newid[$row['category']];

	$newtopic = array(
		'is_sticky'	=> 0,
		'id_board'	=> $boardId,
		'id_member_started' => 1,
		'id_member_updated' => 1,
		'id_poll'	=> 0,
		'id_previous_board' => 0,
		'id_previous_topic' => 0,
		'num_replies'	=> 0,
		'num_views'	=> 0,
		'locked'	=> 0,
		'unapproved_posts' => 0,
		'approved'	=> 1,
	);

	$topicId = insert('smf_topics', $newtopic);

	$newmsg = array(
		'id_board'	=> $boardId,
		'id_topic'	=> $topicId,
		'poster_time'	=> $timeCur,
		'id_member'	=> 1,
		'subject'	=> $row['title'],
		'poster_name'	=> 'zoodb',
		'poster_email'	=> 'admin@danusya.net',
		'poster_ip'	=> '85.143.112.34',
		'smileys_enabled' => '1',
		'modified_time'	=> '0',
		'modified_name'	=> '',
		'body'		=> '[center][b]'.$row['latin'].'
'.$row['english'].'
'.$row['russian'].'[/b]

[url=http://zoodb.ru/'.$row['url'].']ZOO DATABASE[/url]

[url=/]Галерея изображений[/url][/center]',
		'icon'		=> 'clip',
		'approved'	=> 1,
	);

	$messageId = insert('smf_messages', $newmsg);
	$msgIdEsc = mysql_real_escape_string($messageId);
	mysql_query('UPDATE `smf_messages` SET id_msg_modified="'.$msgIdEsc.'" WHERE id_msg="'.$msgIdEsc.'"');
	mysql_query('UPDATE `smf_topics` SET id_first_msg="'.$msgIdEsc.'", id_last_msg="'.$msgIdEsc.'" WHERE id_topic="'.mysql_real_escape_string($topicId).'"');
	mysql_query('UPDATE `smf_boards` SET id_last_msg="'.$msgIdEsc.'", id_msg_updated="'.$msgIdEsc.'" WHERE id_board="'.mysql_real_escape_string($boardId).'"');
}


foreach($board_id2newid as $id => $newid) {
	$ret = mysql_query("SELECT COUNT(*) topic_count FROM smf_topics WHERE id_board='".mysql_real_escape_string($newid)."'");
	$res = mysql_fetch_assoc($ret);

	$topic_count = $res['topic_count'];

	$ret = mysql_query("SELECT COUNT(*) message_count FROM smf_messages WHERE id_board='".mysql_real_escape_string($newid)."'");
	$res = mysql_fetch_assoc($ret);

	$message_count = $res['message_count'];

	mysql_query('UPDATE smf_boards SET num_topics="'.mysql_real_escape_string($topic_count).'", num_posts="'.mysql_real_escape_string($message_count).'" WHERE id_board="'.mysql_real_escape_string($newid).'"');
}

?>

<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("karnaf_header.php");
CheckOperSession();
show_title("Open Tickets");
make_menus("Karnaf (HelpDesk)");
if(isset($_GET['status'])) $status = $_GET['status'];
else $status = 1;
if(!$status) safe_die("Invalid status!");
?>
<script>
var time = new Date().getTime();
$(document.body).bind("mousemove keypress", function(e) {
    time = new Date().getTime();
});

function refresh() {
    if(new Date().getTime() - time >= 60000) 
        window.location.reload(true);
    else 
        setTimeout(refresh, 10000);
}

setTimeout(refresh, 10000);
</script>
<form name="form1" id="form1" method="get">
<select name="status" onChange="form1.submit();">
<option value="999"<? if($status == 999) echo " SELECTED"; ?>>Opened - All non-closed tickets</option>
<?
$query2 = squery("SELECT status_id,status_name FROM karnaf_statuses WHERE status_id!=0 AND status_id!=5 ORDER BY status_id");
while($result2 = sql_fetch_array($query2)) {
?>
<option value="<?=$result2['status_id']?>"<? if($status == $result2['status_id']) echo " SELECTED"; ?>><?=$result2['status_name']?></option>
<?
}
sql_free_result($query2);
?>
</select>
<?
if(isset($_GET['oper'])) $rep_u = $_GET['oper'];
else $rep_u = "";
?>
<select name="oper" onChange="form1.submit();">
<option value="">---</option>
<option value="none"<? if(strtolower($rep_u) == "none") echo " SELECTED"; ?>>*** Not Assigned ***</option>
<?
$query2 = squery("SELECT DISTINCT(rep_u) FROM karnaf_tickets WHERE status!=0 AND rep_u!='' ORDER BY rep_u");
while($result2 = sql_fetch_array($query2)) {
?>
<option value="<?=$result2['rep_u']?>"<? if($result2['rep_u'] == $rep_u) echo " SELECTED"; ?>><?=$result2['rep_u']?></option>
<?
}
sql_free_result($query2);
?>
</select>
</form>
<br><br>
<table border="1" width="90%" style="border-collapse: collapse" bordercolor="#111111" cellpadding="1" cellspacing="1">
<tr class="Karnaf_L_Head">
<td>ID</td>
<td>User</td>
<td>Title</td>
<td>Assigned to</td>
<td>Priority</td>
<td>Open Date</td>
<td>Actions</td>
<td>Duration</td>
<td>Note</td>
</tr>
<?
$qstr = "SELECT t.id,t.randcode,t.status,t.title,t.description,t.unick,t.ufullname,t.uemail,t.uphone,t.ulocation,t.uip,t.rep_u,
t.rep_g,t.open_time,t.opened_by,t.is_real,t.is_private,t.email_upd,t.memo_upd,c1.name AS cat1_name,c2.name AS cat2_name,c3.name AS
cat3_name,s.status_name,up.priority_name AS upriority,t.priority,sp.priority_name AS spriority,t.last_note 
FROM (karnaf_tickets AS t INNER JOIN karnaf_cat3 AS c3 ON c3.id=t.cat3_id INNER JOIN karnaf_cat2 AS c2 ON c2.id=c3.parent
INNER JOIN karnaf_cat1 AS c1 ON c1.id=c2.parent INNER JOIN karnaf_statuses AS s ON s.status_id=t.status INNER JOIN karnaf_priorities AS up ON
up.priority_id=t.upriority INNER JOIN karnaf_priorities AS sp ON
sp.priority_id=t.priority) WHERE";
$argv = array();
if($status == 999) {
  $qstr .= " (t.status!=0 and t.status!=5)";
}
else {
  $qstr .= " t.status=%d";
  array_push($argv, $status);
}
if(isset($_GET['oper']) && !empty($_GET['oper'])) {
  $qstr .= " AND rep_u='%s'";
  if(strtolower($_GET['oper']) == "none") array_push($argv, "");
  else array_push($argv, $_GET['oper']);
}
else if(isset($_GET['rep_u']) && !empty($_GET['rep_u'])) {
  $qstr .= " AND rep_u='%s'";
  array_push($argv, $_GET['rep_u']);
}
if(isset($_GET['group'])) {
  $qstr .= " AND rep_g='%s'";
  array_push($argv, $_GET['group']);
}
else if(isset($_GET['rep_g'])) {
  $qstr .= " AND rep_g='%s'";
  array_push($argv, $_GET['rep_g']);
}
$qstr .= " ORDER BY t.priority DESC,t.open_time";
$limit = 100;
if(isset($_GET['start'])) $start = (int)$_GET['start'];
else $start = 0;
$qstr .= " LIMIT ".$start.",".($limit+1);
$cnt = 0;
array_unshift($argv, $qstr);
$query = squery_args($argv);
while($result = sql_fetch_array($query)) {
  if(!IsGroupMember($result['rep_g']) && !IsKarnafAdminSession()) continue; /* Skip tickets for other teams */
  $cnt++;
  if($cnt > $limit) break;
  $query2 = squery("SELECT count(*) AS count FROM karnaf_actions WHERE tid=%d", $result['id']);
  if($result2 = sql_fetch_array($query2)) $action_cnt = (int)$result2['count'];
  else $action_cnt = 0;
  sql_free_result($query2);
  $query2 = squery("SELECT count(*) AS count FROM karnaf_replies WHERE tid=%d", $result['id']);
  if($result2 = sql_fetch_array($query2)) $reply_cnt = (int)$result2['count'];
  else $reply_cnt = 0;
  sql_free_result($query2);
  $status_style = "Karnaf_P_Normal"; // Lightgreen
  if(isodd($cnt)) $curcol = "col2";
  else $curcol = "col1";
  $priority = (int)$result['priority'];
  if($priority < 0) $status_style = "Karnaf_P_Low"; // LightBlue
  if($priority > 19) $status_style = "Karnaf_P_High"; // Red
  if($priority > 29) $status_style = "Karnaf_P_Critical";
  if($priority < 20) {
    # Mark USA, China and Ukraine as special.
    if(strstr($result['ulocation'],"China")) $status_style = "Karnaf_P_Special";
    if(strstr($result['ulocation'],"USA") || strstr($result['ulocation'],"US-")) $status_style = "Karnaf_P_Special";
    if(strstr($result['ulocation'],"Ukrain")) $status_style = "Karnaf_P_Special";
    if(endsWith($result['uemail'], 'supersonic.com')) $status_style = "Karnaf_P_Special2";
  }
  $body = "";
  if(!empty($result['title'])) $body = "Title: ".$result['title']."\n\n";
  $body .= $result['description'];
  $body = str_replace("\"","''",$body);
  $body = str_replace("<","&lt;",$body);
?>
<tr class="<?=$curcol?>" style="cursor:pointer" onmouseover="this.style.backgroundColor='LightGreen'; this.style.color='Black'" onmouseout="this.style.backgroundColor=''; this.style.color=''" onclick=javascript:window.location.href="edit.php?id=<?=$result['id']?>">
<td class="<?=$status_style?>" align="center"><span title="<?=$body?>" style="cursor:pointer"><?=$result['id']?></span></td>
<td>
<?
$userinfo = ($result['unick']=="Guest"?$result['uemail']:$result['unick']);
if(!defined("IRC_MODE") && !empty($result['ufullname'])) $userinfo = $result['ufullname'];
if(strlen($userinfo) > 30) $userinfo = substr($userinfo,0,30)."...";
echo $userinfo;
?>
</td>
<td><?=str_replace("<","&lt;",$result['title'])?></td>
<?
  if($result['rep_u'] == $nick) echo "<td bgcolor=\"green\">".$result['rep_u']."</td>\n";
  else if(!empty($result['rep_u'])) echo "<td><span title=\"".$result['rep_g']."\" style=\"cursor:pointer\">".$result['rep_u']."</span></td>\n";
  else if(IsGroupMember($result['rep_g'])) echo "<td class=\"karnaf_my_team\">".$result['rep_g']."</td>\n";
  else echo "<td class=\"karnaf_not_my_team\">".$result['rep_g']."</td>\n";
?>
<td><?=$result['spriority']?></td>
<td><?=showtime($result['open_time'])?></td>
<td>
<?=$action_cnt+$reply_cnt?>
</td>
<td><?=do_duration(time() - $result['open_time'])?></td>
<td><?=empty($result['last_note'])?"<center>N/A</center>":$result['last_note']?></td>
</tr>
<?
}
if(!$cnt) echo "<tr><td colspan=\"9\" align=\"center\">*** None ***</td></tr>";
?>
</table>
<?
if($cnt > $limit) {
#  if(strstr($myurl,"?")) $myurl .= "&";
#  else $myurl .= "?";
  $q = "?";
  if(isset($_GET['status'])) $q .= "status=".$_GET['status']."&";
  $q .= "start=".($start+$limit);
  #echo "<center>";
  if($start>0) {
    $q2 = "?";
    if(isset($_GET['status'])) $q2 .= "status=".$_GET['status']."&";
    $q2 .= "start=".($start-$limit);
    echo "<a href=\"".$q2."\">Previous Page</a> | ";
  }
  echo "<a href=\"".$q."\">Next Page</a>";
  #echo "</center>";
  echo "<br>";
  $cnt--;
}
?>
<br>
Total: <?=$cnt?> ticket(s).
<?
sql_free_result($query);
require_once("karnaf_footer.php");
?>

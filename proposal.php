<?php
require_once 'init.php';
connectDB();
requireLogin();
require_once 'util.php';
require_once 'scheduler.php';
getDatabase();

if (!isset($_GET['id']))
    die('no proposal selected');
else
    $proposal_id = $_GET['id'];



function newBatchMenu($name,$batchlist)
    {
    $retstr = "<select name='$name'>\n";
    $festival = getFestivalID();
    $stmt = dbPrepare('select id,name from batch where festival=? order by name');
    $stmt->bind_param('i',$festival);
    $stmt->execute();
    $stmt->bind_result($id,$name);
    while ($stmt->fetch())
        {
        if (!array_key_exists($id,$batchlist))
            {
            $retstr .= "<option value='$id'>" . stripslashes($name) . "</option>\n";
            }
        }
    $stmt->close();
    $retstr .= "</select>\n";
    return $retstr;
    }


function hasAccess($user, $mode, $access)
    {
    if (!$access)
        return false;
    if (!isset($access[$user]))
        return false;
    return in_array($mode,$access[$user]);
    }

function proposalSideControlDiv($proposal_id,$deleted,$proposer_id,$access)
    {
    $batchdiv = "<!--BEGIN SIDECONTROLDIV--><div style='float:right; width:20%'>\n";
    $batchlist = array();
    $stmt = dbPrepare('select batch_id,name from proposalBatch join batch on batch_id=batch.id where proposal_id=?');
    $stmt->bind_param('i',$proposal_id);
    $stmt->execute();
    $stmt->bind_result($batch_id,$batch_name);
    while ($stmt->fetch())
        {
        $batchlist[$batch_id] = $batch_name;
        $batchdiv .= "<span style='white-space:nowrap'><a href='batchMove.php?id=$batch_id&cur=$proposal_id&dir=-1'>&lt;-</a><a href='batch.php?id=$batch_id'>$batch_name</a><a href='batchMove.php?id=$batch_id&cur=$proposal_id&dir=1'>-&gt;</a>&nbsp;&nbsp;&nbsp;<form method='POST' action='api.php' style='display:inline'><input type='hidden' name='command' value='removeFromBatch' /><input type='hidden' name='proposal' value='$proposal_id' /><input type='hidden' name='batch' value='$batch_id'><input type='submit' name='submit' value='x' style='border:0px; padding:0; background: yellow'/></form></span><br>\n";
        }
    $stmt->close();
    $batchdiv .= "<form method='POST' action='api.php' style='white-space:nowrap'><input type='hidden' name='command' value='addToBatch' /><input type='hidden' name='proposal' value='$proposal_id' /><input type='submit' name='submit' value='add to'/>" . newBatchMenu('batch',$batchlist) . "</form>\n";
    if ($deleted)
        $batchdiv .= "<form method='POST' action='api.php'><input type='hidden' name='command' value='undeleteProposal' /><input type='hidden' name='id' value='$proposal_id' /><input type='submit' value='undelete project' /></form>\n";
    else
        $batchdiv .= "<form method='POST' action='api.php'><input type='hidden' name='command' value='deleteProposal' /><input type='hidden' name='id' value='$proposal_id' /><input type='submit' value='delete project' /></form>\n";
    if (!hasAccess($proposer_id,'viewschedule',$access))
        $batchdiv .= beginApiCallHtml('grantProposalAccess',array('proposal'=>$proposal_id,'user'=>$proposer_id,'mode'=>'viewschedule')) . endApiCallHtml('allow proposer to view schedule');
    $batchdiv .= "<br><a href=\"proposalForm.php?id=$proposal_id\">[original form]</a>\n";
    $batchdiv .= "<br><br>\n";
    $notes = getNotes($proposal_id);
    foreach ($notes as $n)
        {
        $batchdiv .= noteDiv($n,$proposal_id);
        }
    $batchdiv .= "<form method='POST' action='api.php'><input type='hidden' name='command' value='addNote' /><input type='hidden' name='entity' value='$proposal_id' /><textarea name='note' rows='2' cols='30'></textarea><br><input type='submit' name='submit' value='add note'/></form>\n";
    $batchdiv .= "</div><!--END SIDECONTROLDIV-->\n";
    return $batchdiv;
    }


function jsSafe($s)
    {
    $s = addslashes($s);
    $s = str_replace("\n","",$s);
    $s = str_replace("\r","",$s);
    return $s;
    }

function proposalPageHeader()
    {
    $header = <<<ENDSTRING
<script src="jquery.min.js" type="text/javascript"></script>
<script type="text/javascript">
var availability = {
ENDSTRING;

    global $festivalNumberOfDays, $availability;
    for ($i=0; $i < $festivalNumberOfDays; $i++)
        if (is_array($availability) && array_key_exists($i,$availability))
            $header .= " $i : '" . dayToDateday($i) . ': ' . jsSafe($availability[$i]) . "',";

    $header .= <<<ENDSTRING
};
function showEditor(name)
    {
    $('#show_' + name).hide();
    $('#edit_' + name).show();
    $('#donteditButton_' + name).show();
    $('#editButton_' + name).hide();
    }
function hideEditor(name)
    {
    shownode = $('#show_'+name);
    editnode = $('#edit_'+name);
    inputnode = $('#input_'+name);
    shownode.show();
    origtext = shownode.html();
    inputnode.val(origtext);
    editnode.hide();
    $('#donteditButton_' + name).hide();
    $('#editButton_' + name).show();
    }
function showScheduler(name)
    {
    $('.scheduleForm').hide()
    $(name).show()
    }
function toggleEdit(rowname)
    {
    $('#' + rowname).toggle()
    $('#' + rowname + 'edit').toggle()
    }
function hoverFunc()
    {
    $('.availabilityInfo').html(availability[$(this).data("bifday")]);
    }
function unhoverFunc()
    {
    $('.availabilityInfo').html('&nbsp;');
    }
function limitChars(node, limit)
    {
    var text = node.val();
    if (text.length > limit)
        {
        node.val(text.substr(0,limit));
        return false;
        }
     else
        {
        return true;
        }
     }
 
$(document).ready(function() {
    $('.edit_info').hide();
    $('.calEntry').hover(hoverFunc,unhoverFunc);
ENDSTRING;

    if (!hasPrivilege('scheduler'))
        $header .= "$('.brochure_description').keyup(function(){ limitChars($(this), 140) });\n";

    $header .= <<<ENDSTRING
 });
</script>
ENDSTRING;
    return $header;
    }


function proposalScheduleDiv($proposal_id,$proposal,$canEditSchedule)
    {
    $schedulediv = "<!--BEGIN SCHEDULE--><div class='schedule'>\n";
    $schedulediv .= "<table>\n";
    foreach ($proposal->listings as $listing)
        {
        if (($listing->proposalid == $proposal_id) && ($canEditSchedule))
            $schedulediv .= editableListingRow($listing->id,1,1,1,0,0);
        else
            $schedulediv .= listingRow($listing->id,1,1,1,1,1);
        }
    $schedulediv .= "</table>\n";
    $schedulediv .= "</div><!--END SCHEDULE-->\n";
    return $schedulediv;
    }


function proposalGroupShowPerformers($proposal,$canEditSchedule)
    {
    $out = "<!--BEGIN PERFORMERS--><div>\n";
    $out .= "<p>Performers:</p><table rules='all'>\n";
    $count = 0;
    foreach ($proposal->performers as $perf)
        {
        $count++;
        if ($perf->cancelled)
            $tdtags = ' class="cancelled"';
        else
            $tdtags = '';
        $out .= '<tr id="groupschedulerow' . $count . '">';
        $out .= '<td' . $tdtags . '><a href="proposal.php?id=' . $perf->performerid . '">' . $perf->performer->title . '</a></td>';
        $out .= '<td' . $tdtags . '>' . $perf->showorder . '</td>';
        $out .= '<td' . $tdtags . '>' . timeToString($perf->time) . '</td>';
        $out .= '<td' . $tdtags . '>' . $perf->note . '</td>';
        if ($canEditSchedule)
            {
            $out .= '<td' . $tdtags . '> &nbsp;<a href="" onclick="toggleEdit(\'groupschedulerow' . $count . '\'); return false;">edit</a>&nbsp; </td>';
            }
        $out .= '</tr>';
 
        if ($canEditSchedule)
            {
            $out .= '<tr id="groupschedulerow' . $count . 'edit" style="display:none">';
            $out .= '<td><a href="proposal.php?id=' . $perf->performerid . '">' . $perf->performer->title . '</a></td>';
            $out .= '<td colspan="3">' . beginApiCallHtml('changeGroupPerformer',array('groupperformerid'=>$perf->id));
            $out .= '<input type="text" name="showorder" size="2" value="' . $perf->showorder . '" />';
            $out .= timeMenu(11,28,'time',$perf->time);
            $out .= '<input type="text" name="note" value="' . $perf->note . '" />';
            $out .= '<input type="submit" value="Save" />';
            $out .= '</form></td>';
            $out .= '<td>';
            if ($perf->cancelled)
                $out .= beginApiCallHtml('uncancelGroupPerformer',array('groupperformerid'=>$perf->id)) . endApiCallHtml('uncancel');
            else
                $out .= beginApiCallHtml('cancelGroupPerformer',array('groupperformerid'=>$perf->id)) . endApiCallHtml('cancel');
            $out .= beginApiCallHtml('deleteGroupPerformer',array('groupperformerid'=>$perf->id)) . endApiCallHtml('delete');
            $out .= '<a href="" onclick="toggleEdit(\'groupschedulerow' . $count . '\'); return false;">don\'t edit</a></td>';
            }
        $out .= '</tr>';
        }
    $out .= "</table>\n";
    $out .= "</div><!--END PERFORMERS-->\n";
    return $out;
    }


function calEntry($day)
    {
    return '<input type="checkbox" name="' . dayToDate($day) . '" value="1" />';
    }

function calEntry2($day)
    {
    return '<input type="checkbox" name="' . dayToDate($day) . '" value="1" checked/>';
    }

function proposalSchedulingDiv($id)
    {
    global $proposalList;
    $proposal = $proposalList[$id];
    $out = '<div class="schedulebox">[scheduling]<br/>';
    $out .= '<a href="" id="scheduleEventAnchor" onclick="showScheduler(\'#scheduleEventForm\'); return false">performance</a>&nbsp;<a href="" id="scheduleInstallationAnchor" onclick="showScheduler(\'#scheduleInstallationForm\'); return false">installation</a>&nbsp;&nbsp;';
    if ($proposal->isgroupshow)
        {
        $out .= '<a href="" id="schedulePerformerAnchor" onclick="showScheduler(\'#schedulePerformerForm\'); return false">performer</a>&nbsp;&nbsp;';
/*
        $out .= '<a href="" id="groupListModeAnchor" onclick="showScheduler(\'#groupListModeForm\'); return false">group-list-mode</a>&nbsp;&nbsp;';
*/
        }
    else
        $out .= '<a href="" id="scheduleGroupAnchor" onclick="showScheduler(\'#scheduleGroupForm\'); return false">group show</a>&nbsp;&nbsp;';
 
/*
    $out .= '<a href="" id="scheduleMassdeleteAnchor" onclick="showScheduler(\'#scheduleMassdeleteForm\'); return false">mass-delete</a>&nbsp;&nbsp;';
*/
    $out .= scheduleEventForm('proposal.php?id=' . $id, 'calEntry', $id, 0);
 
    $out .= scheduleInstallationForm('proposal.php?id=' . $id, 'calEntry2', $id, 0);
 
    if ($proposal->isgroupshow)
        {
        $out .= "<div class='scheduleForm' id='schedulePerformerForm' style='display: none'>\n";
        $out .= "<form method='POST' action='api.php'>\n";
        $out .= "<input type='hidden' name='command' value='scheduleGroupPerformer' />\n";
        $out .= "<input type='hidden' name='groupeventid' value='$id' />\n";
        $out .= "<input type='hidden' name='returnurl' value='proposal.php?id=$id' />\n";
        $out .= "<table>\n";
        $out .= "<tr>\n";
        $out .= "<td> " . showMenu('performerid',getProposalInfo($proposal->id,'batch')) . " </td>\n";
        $out .= "<td> Time " . timeMenu(11,28,'time') . " </td>\n";
        $out .= "<td> Order <input type='text' name='order' value='0' size='3'/> </td>\n";
        $out .= "<td> Note <input type='text' name='note' value='' size='12'/> </td>\n";
        $out .= "<td><input type='submit' name='submit' value='Add' /></td>\n";
        $out .= "</tr>\n";
        $out .= "</table>\n";
        $out .= "</form>\n";
        $out .= "</div>\n";
/*
        $out .= '<div class="scheduleForm" id="groupListModeForm" style="display:none">';
        $out .= 'Display performers list with:<form method="POST" action="api.php">';
        $out .= '<input type="hidden" name="command" value="groupListMode" />';
        $out .= '<input type="hidden" name="groupeventid" value="' . $id . '" />';
        $out .= '<input type="radio" name="grouplistmode" value="0" ';
        if ($info['grouplistmode'] == 0) $out .= 'checked ';
        $out .= '/>numbers<br/>';
        $out .= '<input type="radio" name="grouplistmode" value="1" ';
        if ($info['grouplistmode'] == 1) $out .= 'checked ';
        $out .= '/>times<br/>';
        $out .= '<input type="radio" name="grouplistmode" value="2" ';
        if ($info['grouplistmode'] == 2) $out .= 'checked ';
        $out .= '/>both<br/>';
        $out .= '<input type="submit" name="submit" value="Save" />';
        $out .= '</form>';
        $out .= '</div>';
*/
        }
    else
        {
        $out .= "<div class='scheduleForm' id='scheduleGroupForm' style='display: none'>\n";
        $out .= "<form method='POST' action='api.php'>\n";
        $out .= "<input type='hidden' name='command' value='scheduleGroupPerformer' />\n";
        $out .= "<input type='hidden' name='performerid' value='$id'>\n";
        $out .= "<input type='hidden' name='returnurl' value='proposal.php?id=$id'>\n";
        $out .= "<table>\n";
        $out .= "<tr>\n";
        $out .= "<td> " . groupShowMenu('groupeventid') . " </td>\n";
        $out .= "<td> Time " . timeMenu(11,28,'time') . " </td>\n";
        $out .= "<td> Order <input type='text' name='order' value='0' size='3'/> </td>\n";
        $out .= "<td> Note <input type='text' name='note' value='' size='12'/> </td>\n";
        $out .= "<td><input type='submit' name='submit' value='Add'></td>\n";
        $out .= "</tr>\n";
        $out .= "</table>\n";
        $out .= "</form>\n";
        $out .= "</div>\n";
        }
/*
    $out .= '<div class="scheduleForm" id="scheduleMassdeleteForm" style="display: none">';
    $out .= '<form method="POST" action="api.php">';
    $out .= '<input type="hidden" name="command" value="massDelete" />';
    $out .= '<input type="hidden" name="proposal" value="' . $id . '" />';
    $out .= '<input type="hidden" name="date" value="*" />';
    $out .= 'Delete all shows at ' . venueMenu('venue');
    $out .= '<input type="submit" name="submit" value="Delete" />';
    $out .= '</form>';
    $out .= '</div>';
*/
    $out .= '</div><br>';
    return $out;
    }


function availTable($proposal_id,$av)
    {
    global $festivalNumberOfDays;
    $s = "<table>\n";
    if (is_array($av))
        {
        for ($i=0; $i < $festivalNumberOfDays; $i++)
            {
            if (array_key_exists($i,$av))
                {
                $s .= "<tr id='edit_avail$i' class='edit_info'><th>" . dayToDateday($i) . "</th><td><form method='POST' action='api.php'><input type='hidden' name='command' value='changeProposalAvail' /><input type='hidden' name='proposal' value='$proposal_id' /><input type='hidden' name='daynum' value='$i' /><textarea id='input_avail$i' name='newinfo' cols='40'>" . $av[$i] . "</textarea><input type='submit' name='submit' value='save'><button onclick='hideEditor(\"avail$i\"); return false;'>don't edit</button></td></form></tr>\n";
                $s .= "<tr id='show_avail$i' class='show_info'><th>" . dayToDateday($i) . "<span class='fieldEditLink' onclick='showEditor(\"avail$i\");'>[edit]</span></th><td>" . $av[$i] . "</td></tr>\n";
//                $s .= "<tr><td>" . dayToDateday($i) . "</td><td>" . $av[$i] . "</td></tr>\n";
                }
            }
        }
    $s .= "</table>\n";
    return $s;
    }


$stmt = dbPrepare('select `proposerid`, `proposal`.`festival`, `title`, `info`, `availability`, `forminfo`, `orgcontact`, `deleted`, `submitted`, `user`.`name`, `festival`.`name`, `proposal`.`access` from `proposal` join `user` on `proposerid`=`user`.`id` join `festival` on `proposal`.`festival`=`festival`.`id` where `proposal`.`id`=?');
$stmt->bind_param('i',$proposal_id);
$stmt->execute();
$stmt->bind_result($proposer_id,$festival_id,$title,$info_ser,$availability_ser,$forminfo_ser,$orgcontact,$deleted,$submitted,$proposer_name, $festivalname, $access_ser);
$stmt->fetch();
$stmt->close();
$info = unserialize($info_ser);
$availability = unserialize($availability_ser);
$forminfo = unserialize($forminfo_ser);
$access = unserialize($access_ser);

if (!hasPrivilege(array('scheduler','organizer')))
    {
    if ($proposer_id != $_SESSION['userid'])
        {
        header('Location: .');
        die();
        }
    }

$orgcontactinfo = dbQueryByID('select `name`,`card`.`id`,`card`.`email` from `user` join `card` on `user`.`id`=`card`.`userid` where `user`.`id`=?',$orgcontact);


bifPageheader('proposal: ' . $title, proposalPageHeader());

$canSeeSchedule = hasAccess($_SESSION['userid'],'viewschedule',$access) || hasPrivilege(array('scheduler','organizer'));
$canEditSchedule = hasPrivilege('scheduler');
$proposal = $proposalList[$proposal_id];
if ($canSeeSchedule)
    echo proposalScheduleDiv($proposal_id,$proposal,$canEditSchedule);

if ($proposal->isgroupshow)
    echo proposalGroupShowPerformers($proposal,$canEditSchedule);

if (hasPrivilege('scheduler'))
    echo proposalSchedulingDiv($proposal_id);

/*
echo "<div><a href=\"imageUpload.php?id=$proposal_id\">upload image for web</a></div>\n";
*/

if (hasPrivilege('scheduler'))
    echo proposalSideControlDiv($proposal_id, $deleted, $proposer_id, $access);

$html = '';
$html .= '<span>(<b>NOTE: when editing, you must save any changed field before going to edit another field</b>)</span>';
$html .= '<table cellpadding="3">';

$html .= "<tr>\n<th>Title <span class='fieldEditLink' id='editButton_fieldTitle' onclick='showEditor(\"fieldTitle\");'>[edit]</span><span class='fieldDontEditLink' id='donteditButton_fieldTitle' onclick='hideEditor(\"fieldTitle\");'>[don't edit]</span></th>\n<td>";
$html .= "<div id='show_fieldTitle' class='show_info'>" . htmlspecialchars($title,ENT_COMPAT | ENT_HTML5, "UTF-8") . "</div>\n";
$html .= "<div id='edit_fieldTitle' class='edit_info'><form method='POST' action='api.php'><input type='hidden' name='command' value='changeProposalTitle' /><input type='hidden' name='proposal' value='$proposal_id' /><input id='input_fieldTitle' type='text' name='newtitle' value=\"". htmlspecialchars($title,ENT_COMPAT | ENT_HTML5, "UTF-8") . "\" /><input type='submit' name='submit' value='save'></form></div>\n";
$html .= "</td>\n</tr>\n";

$html .= "<tr><th>Festival</th><td>$festivalname</td></tr>\n";

$html .= "<tr><th>Proposer</th><td><a href='user.php?id=$proposer_id'>$proposer_name</a>";
if (hasPrivilege('scheduler'))
    $html .= "&nbsp;&nbsp;&nbsp;(<a href=\"changeOwner.php?id=$proposal_id\">change proposer</a>)";
$html .= "</td></tr>\n";
$html .= "<tr><th>Festival contact</th><td><a href='card.php?id=$orgcontactinfo[id]'>$orgcontactinfo[name]</a> ($orgcontactinfo[email])</td></tr>\n";
foreach ($info as $fieldnum=>$v)
    {
    $html .= "<tr id='edit_field$fieldnum' class='edit_info'>\n<th>$v[0]</th>\n";
    $html .= "<td>" . beginApiCallHtml('changeProposalInfo', array('proposal'=>"$proposal_id", 'fieldnum'=>"$fieldnum"));
    $html .= "<textarea id='input_field$fieldnum' name='newinfo' cols='80'";
    if ($v[0] == 'Description for brochure')
        $html .=  " class='brochure_description'";
    $html .= ">$v[1]</textarea>\n<input type='submit' name='submit' value='save'><button onclick='hideEditor(\"field$fieldnum\"); return false;'>don't edit</button>";
    if ($v[0] == 'Description for brochure')
        $html .= "<div class='brochure_description_warning'>(max 140 characters)</div>\n";
    $html .= "</form></td></tr>\n";
    $html .= "<tr id='show_field$fieldnum' class='show_info'>\n<th>$v[0] <span class='fieldEditLink' onclick='showEditor(\"field$fieldnum\");'>[edit]</span></th>\n<td>" . multiline($v[1]) . "</td></tr>\n";
    }
if (hasPrivilege('scheduler'))
    {
    $html .= "<tr id='edit_fieldNew' class='edit_info'><th>[add field]</th><td><form method='POST' action='api.php'><input type='hidden' name='command' value='addProposalInfoField' /><input type='hidden' name='proposal' value='$proposal_id' /><input type='text' name='fieldname'><input type='submit' name='submit' value='add'><button onclick='hideEditor(\"fieldNew\"); return false;'>don't add</button></form></td></tr>\n";
    $html .= "<tr id='show_fieldNew' class='show_info' onclick='showEditor(\"fieldNew\");'><th style='background:#ff8'>[add field]</th><td>&nbsp;</td></tr>\n";
    }
$html .= "<tr><th>Availability</th><td>" . availTable($proposal_id,$availability) . "</td></tr>\n";
$html .= '<tr><th>Submitted</th><td>' . $submitted . '</td></tr>';

$html .= '</table>';

echo $html;

bifPagefooter();
?>

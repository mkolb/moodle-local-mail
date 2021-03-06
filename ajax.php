<?php

// Local mail plugin for Moodle
// Copyright © 2012,2013 Institut Obert de Catalunya
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
// Ths program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot . '/local/mail/lib.php');

$action     = optional_param('action', false, PARAM_ALPHA);
$type       = optional_param('type', false, PARAM_ALPHA);
$msgs       = optional_param('msgs', '', PARAM_SEQUENCE);
$labelids   = optional_param('labelids', '', PARAM_SEQUENCE);
$labeltsids = optional_param('labeltsids', '', PARAM_SEQUENCE);
$deletelabel= optional_param('deletelabel', '', PARAM_INT);
$itemid     = optional_param('itemid', 0, PARAM_INT);
$messageid  = optional_param('m', 0, PARAM_INT);
$offset     = optional_param('offset', 0, PARAM_INT);
$perpage    = optional_param('perpage', 0, PARAM_INT);
$sesskey    = optional_param('sesskey', null, PARAM_RAW);
$mailview   = optional_param('mailview', false, PARAM_BOOL);
$labelname  = optional_param('labelname', false, PARAM_TEXT);
$labelcolor = optional_param('labelcolor', false, PARAM_ALPHANUMEXT);
$search     = optional_param('search', '', PARAM_RAW);
$groupid    = optional_param('groupid', 0, PARAM_INT);
$roleid     = optional_param('roleid', 0, PARAM_INT);
$roleids    = optional_param('roleids', '', PARAM_SEQUENCE);
$recipients = optional_param('recipients', '', PARAM_SEQUENCE);
$undo       = optional_param('undo', false, PARAM_BOOL);
//Search messages
$searching  = optional_param('searching', false, PARAM_BOOL);
$time       = optional_param('time', '', PARAM_SEQUENCE);
$unread     = optional_param('unread', '', PARAM_TEXT);
$before     = optional_param('before', 0, PARAM_INT);
$after      = optional_param('after', 0, PARAM_INT);
$perpageid  = optional_param('perpageid', 0, PARAM_INT);

define('MAIL_MAXUSERS', 100);

$courseid = ($type == 'course'?$itemid:$SITE->id);
require_login($courseid);

$valid_actions = array(
    'starred',
    'unstarred',
    'delete',
    'restore',
    'markasread',
    'markasunread',
    'prevpage',
    'nextpage',
    'perpage',
    'viewmail',
    'goback',
    'assignlabels',
    'newlabel',
    'setlabel',
    'deletelabel',
    'getrecipients',
    'updaterecipients',
    'search'
);

$nomessageactions = array(
    'prevpage',
    'nextpage',
    'perpage',
    'goback',
    'setlabel',
    'search'
);

if ($action and in_array($action, $valid_actions) and !empty($USER->id)) {

    if(!confirm_sesskey($sesskey)) {
        echo json_encode(array('msgerror' => get_string('invalidsesskey', 'error')));
        die;
    }
    $params = array();
    $offset = max(0, $offset);

    if (empty($msgs) and !in_array($action, $nomessageactions)){
        echo json_encode(array('msgerror' => get_string('nomessageserror', 'local_mail')));
        die;
    }
    if(!in_array($action, $nomessageactions)) {
        if ($action == 'viewmail' or $action == 'getrecipients' or $action == 'updaterecipients') {
            $message = local_mail_message::fetch($msgs);
            if (!$message or !$message->viewable($USER->id)) {
                echo json_encode(array('msgerror' => get_string('invalidmessage', 'local_mail')));
                die;
            }
        } else {
            $msgsids = explode(',', $msgs);
            $messages = local_mail_message::fetch_many($msgsids);
        }
    }
    $mailpagesize = get_user_preferences('local_mail_mailsperpage', MAIL_PAGESIZE, $USER->id);
    $params = array();
    $searchdata =  array();
    if ($searching) {
        $searchdata = array(
            'pattern' => $search,
            'time' => $time,
            'unread' => $unread,
            'before' => $before,
            'after' => $after,
            'limit' => $mailpagesize,
            'perpageid' => $perpageid
        );
    }

    if ($action === 'starred') {
        $func = 'local_mail_setstarred';
        array_push($params, $messages);
        array_push($params, true);
        array_push($params, '');
    } elseif ($action === 'unstarred') {
        $func = 'local_mail_setstarred';
        array_push($params, $messages);
        array_push($params, false);
        array_push($params, $searchdata);
        array_push($params, array(
                            'type' => $type,
                            'mailview' => $mailview,
                            'itemid' => $itemid,
                            'type' => $type,
                            'offset' => $offset,
                            'mailpagesize' => $mailpagesize
                        ));
    } elseif ($action === 'markasread') {
        $func = 'local_mail_setread';
        array_push($params, $messages);
        array_push($params, true);
    } elseif ($action === 'markasunread') {
        $func = 'local_mail_setread';
        array_push($params, $messages);
        array_push($params, false);
        if ($mailview) {
            if ($type != 'course' and $type != 'label') {
                $itemid = 0;
            }
            array_push($params, array(
                                    'itemid' => $itemid,
                                    'type' => $type,
                                    'offset' => $offset,
                                    'mailpagesize' => $mailpagesize
                                )
            );
        }
    } elseif ($action === 'delete' or $action === 'restore') {
        $func = 'local_mail_setdelete';
        array_push($params, $messages);
        array_push($params, ($action == 'delete'));
        if ($type != 'course' and $type != 'label') {
            $itemid = 0;
        }
        array_push($params, $itemid);
        array_push($params, $type);
        array_push($params, $offset);
        array_push($params, $mailpagesize);
        array_push($params, $undo);
        array_push($params, $searchdata);
    } elseif ($action === 'prevpage') {
        $func = 'local_mail_setprevpage';
        array_push($params, $itemid);
        array_push($params, $type);
        array_push($params, $offset);
        array_push($params, $mailpagesize);
    } elseif ($action === 'nextpage') {
        $func = 'local_mail_setnextpage';
        array_push($params, $itemid);
        array_push($params, $type);
        array_push($params, $offset);
        array_push($params, $mailpagesize);
    }  elseif ($action === 'perpage') {
        $func = 'local_mail_setperpage';
        array_push($params, $itemid);
        array_push($params, $type);
        array_push($params, $offset);
        array_push($params, $perpage);
        array_push($params, $searchdata);
    } elseif ($action === 'viewmail') {
        $func = 'local_mail_getmail';
        array_push($params, $message);
        array_push($params, $type);
        array_push($params, false); //reply
        array_push($params, $offset);
        array_push($params, $itemid);
    } elseif ($action === 'goback') {
        $func = 'local_mail_setgoback';
        if ($type != 'course' and $type != 'label') {
            $itemid = 0;
        }
        array_push($params, $itemid);
        array_push($params, $type);
        array_push($params, $offset);
        array_push($params, $mailpagesize);
        array_push($params, $searchdata);
    } elseif ($action === 'assignlabels') {
        $func = 'local_mail_assignlabels';
        array_push($params, $messages);
        array_push($params, explode(',', $labelids));
        array_push($params, explode(',', $labeltsids));
        array_push($params, array(
                                    'mailview' => $mailview,
                                    'itemid' => $itemid,
                                    'type' => $type,
                                    'offset' => $offset,
                                    'mailpagesize' => $mailpagesize
                                ));
        array_push($params, $searchdata);
    } elseif ($action === 'newlabel') {
        $func = 'local_mail_newlabel';
        $data = array('t='.$type);
        array_push($params, $messages);
        array_push($params, $labelname);
        array_push($params, $labelcolor);
        if ($type == 'course') {
            array_push($data, 'c='.$itemid);
        } elseif ($type == 'label') {
            array_push($data, 'l='.$itemid);
        }
        if ($mailview) {
            array_push($data, 'm='.$messageid);
        }
        if ($offset) {
            array_push($data, 'offset='.$offset);
        }
        array_push($params, $data);
    } elseif ($action === 'deletelabel') {
        $func = 'local_mail_deletelabel';
        array_push($params, $deletelabel);
        $data = array('t='.$type);
        if ($type == 'course') {
            array_push($data, 'c='.$itemid);
        } elseif ($type == 'label') {
            array_push($data, 'l='.$itemid);
        }
        if ($mailview) {
            array_push($data, 'm='.$messageid);
        }
        if ($offset) {
            array_push($data, 'offset='.$offset);
        }
        array_push($params, $data);
    } elseif ($action === 'setlabel') {
        $func = 'local_mail_setlabel';
        array_push($params, $type);
        array_push($params, $itemid);
        array_push($params, $labelname);
        array_push($params, $labelcolor);
    } elseif ($action === 'getrecipients') {
        $func = 'local_mail_getrecipients';
        array_push($params, $message);
        array_push($params, $search);
        array_push($params, $groupid);
        array_push($params, $roleid);
    } elseif ($action === 'updaterecipients') {
        $func = 'local_mail_updaterecipients';
        array_push($params, $message);
        array_push($params, explode(',', $recipients));
        array_push($params, explode(',', $roleids));
    } elseif ($action === 'search') {
        $func = 'local_mail_searchmessages';
        array_push($params, $type);
        array_push($params, $itemid);
        array_push($params, $searchdata);
    }
    echo json_encode(call_user_func_array($func, $params));
} else {
    echo json_encode(array('msgerror' => 'Invalid data'));
}

function local_mail_setstarred ($messages, $bool, $search, $data = false) {
    global $USER;

    $content = '';
    foreach ($messages as $message) {
        if ($message->viewable($USER->id)) {
            $message->set_starred($USER->id, $bool);
        }
    }

    if ($data and !$data['mailview'] and $data['type'] == 'starred' and !$bool) {
        $totalcount = local_mail_message::count_index($USER->id, $data['type'], $data['itemid']);
        if ($data['offset'] > $totalcount - 1) {
           $data['offset'] = min(0, $data['offset']-$data['mailpagesize']);
        }
        if (!empty($search)) {
            return local_mail_searchmessages($data['type'], $data['itemid'], $search, $data['offset']);
        } else {
            $messages = local_mail_message::fetch_index($USER->id, $data['type'], $data['itemid'], $data['offset'], $data['mailpagesize']);
            $content = local_mail_print_messages($data['itemid'], $data['type'], $data['offset'], $messages, $totalcount);
        }
    }
    return array(
        'info' => '',
        'html' => $content
    );
}

function local_mail_setread($messages, $bool, $mailview = false) {
    global $USER;

    $content = '';

    foreach ($messages as $message) {
        if ($message->viewable($USER->id)) {
            $message->set_unread($USER->id, !$bool);
        }
    }

    if ($mailview) {
        $totalcount = local_mail_message::count_index($USER->id, $mailview['type'], $mailview['itemid']);
        $messages = local_mail_message::fetch_index($USER->id, $mailview['type'], $mailview['itemid'], $mailview['offset'], $mailview['mailpagesize']);
        $content = local_mail_print_messages($mailview['itemid'], $mailview['type'], $mailview['offset'], $messages, $totalcount);
    }
    return array(
        'info' => local_mail_get_info(),
        'html' => $content
    );
}

function local_mail_setdelete($messages, $bool, $itemid, $type, $offset, $mailpagesize, $undo, $search) {
    global $USER;

    $ids = array();
    $content = '';
    $totalcount = local_mail_message::count_index($USER->id, $type, $itemid);
    foreach ($messages as $message) {
        if ($message->viewable($USER->id)) {
            $message->set_deleted($USER->id, $bool);
            array_push($ids, $message->id());
            $totalcount += ($undo?1:-1);
        }
    }
    if ($offset > $totalcount-1) {
        $offset = min(0, $offset-$mailpagesize);
    }

    if (!empty($search)) {
        $data = local_mail_searchmessages($type, $itemid, $search, $offset);
        $data['info'] = local_mail_get_info();
        $data['undo'] = implode(",", $ids);
        return $data;
    } else {
        $messages = local_mail_message::fetch_index($USER->id, $type, $itemid, $offset, $mailpagesize);
        $content = local_mail_print_messages($itemid, $type, $offset, $messages, $totalcount);
    }
    return array(
        'info' => local_mail_get_info(),
        'html' => $content,
        'undo' => implode(",", $ids)
    );
}

function local_mail_setprevpage($itemid, $type, $offset, $mailpagesize){
    global $USER;

    $totalcount = local_mail_message::count_index($USER->id, $type, $itemid);
    $offset = max(0, $offset - $mailpagesize);
    $messages = local_mail_message::fetch_index($USER->id, $type, $itemid, $offset, $mailpagesize);
    return array(
        'info' => '',
        'html' => local_mail_print_messages($itemid, $type, $offset, $messages, $totalcount)
    );
}

function local_mail_setnextpage($itemid, $type, $offset, $mailpagesize){
    global $USER;

    $totalcount = local_mail_message::count_index($USER->id, $type, $itemid);
    $offset = $offset + $mailpagesize;
    $messages = local_mail_message::fetch_index($USER->id, $type, $itemid, $offset, $mailpagesize);
    return array(
        'info' => '',
        'html' => local_mail_print_messages($itemid, $type, $offset, $messages, $totalcount)
    );
}

function local_mail_setgoback($itemid, $type, $offset, $mailpagesize, $search) {
    global $USER;

    $totalcount = local_mail_message::count_index($USER->id, $type, $itemid);
    if (!empty($search)) {
        return local_mail_searchmessages($type, $itemid, $search, $offset);
    }
    $messages = local_mail_message::fetch_index($USER->id, $type, $itemid, $offset, $mailpagesize);
    return array(
        'info' => '',
        'html' => local_mail_print_messages($itemid, $type, $offset, $messages, $totalcount)
    );
}

function local_mail_assignlabels($messages, $labelids, $labeltsids, $data, $search)
{
    global $USER;

    $rethtml = false;
    $content = '';

    $labels = local_mail_label::fetch_user($USER->id);
    foreach ($messages as $message) {
        if ($message->viewable($USER->id) and !$message->deleted($USER->id)) {
            foreach ($labels as $label) {
                if (in_array($label->id(), $labelids)) {
                    $message->add_label($label);
                } else {
                    if ($data['type'] == 'label' and $label->id() == $data['itemid']) {
                        $rethtml = true;
                    }
                    if (!in_array($label->id(), $labeltsids)) {
                        $message->remove_label($label);
                    }
                }
            }
        }
    }
    if (!$data['mailview'] && $rethtml) {
        $totalcount = local_mail_message::count_index($USER->id, $data['type'], $data['itemid']);
        if (!empty($search)) {
            return local_mail_searchmessages($data['type'], $data['itemid'], $search, false);
        } else {
            if ($data['offset'] > $totalcount-1) {
               $data['offset'] = min(0, $data['offset']-$data['mailpagesize']);
            }
            $messages = local_mail_message::fetch_index($USER->id, $data['type'], $data['itemid'], $data['offset'], $data['mailpagesize']);
            $content = local_mail_print_messages($data['itemid'], $data['type'], $data['offset'], $messages, $totalcount);
        }
    }
    return array(
        'info' => local_mail_get_info(),
        'html' => $content
    );
}

function local_mail_setperpage($itemid, $type, $offset, $mailpagesize, $search){
    global $USER;

    $totalcount = local_mail_message::count_index($USER->id, $type, $itemid);
    if (in_array($mailpagesize, array (5, 10, 20, 50, 100))) {
        set_user_preference('local_mail_mailsperpage', $mailpagesize);
        if (!empty($search)) {
            $search['limit'] = $mailpagesize;
            return local_mail_searchmessages($type, $itemid, $search, $offset, true);
        } else {
            $messages = local_mail_message::fetch_index($USER->id, $type, $itemid, $offset, $mailpagesize);
            return array(
                'info' => '',
                'html' => local_mail_print_messages($itemid, $type, $offset, $messages, $totalcount)
            );
        }
    }
    return array(
        'info' => '',
        'html' => ''
    );
}

function local_mail_print_messages($itemid, $type, $offset, $messages, $totalcount) {
    global $PAGE, $USER;

    $url = new moodle_url('/local/mail/view.php', array('t' => $type));
    $PAGE->set_url($url);
    $mailoutput = $PAGE->get_renderer('local_mail');
    $content = $mailoutput->view(array(
        'type' => $type,
        'itemid' => $itemid,
        'userid' => $USER->id,
        'messages' => $messages,
        'totalcount' => $totalcount,
        'offset' => $offset,
        'ajax' => true
    ));
    return preg_replace('/^<div[^>]*>|<\/div>$/', '', $content);
}

function local_mail_getmail($message, $type, $reply, $offset, $labelid) {
    global $PAGE, $OUTPUT, $USER;

    $url = new moodle_url('/local/mail/view.php', array('t' => $type));
    $url->param('m', $message->id());
    $PAGE->set_url($url);

    $message->set_unread($USER->id, false);
    $mailoutput = $PAGE->get_renderer('local_mail');
    $content = $mailoutput->toolbar('view', $message->course()->id, false, null, ($type === 'trash'));
    $content .= $mailoutput->notification_bar();
    $content .= $OUTPUT->container_start('mail_view');

    $content .= $OUTPUT->container_start('mail_subject');
    $title = s($message->subject());
    $content .= $mailoutput->label_message($message, $type, $labelid, true);
    $content .= $OUTPUT->heading($title, 3, '');
    if ($type !== 'trash') {
        $content .= $mailoutput->starred($message, $USER->id, $type, 0, true);
    }
    $content .= $OUTPUT->container_end();

    $content .= $mailoutput->mail($message, $reply, $offset);

    $content .= $OUTPUT->container_end();

    $content .= html_writer::start_tag('div');
    $content .= html_writer::empty_tag('input', array(
        'type' => 'hidden',
        'name' => 'sesskey',
        'value' => sesskey(),
    ));

    $content .= html_writer::empty_tag('input', array(
        'type' => 'hidden',
        'name' => 'type',
        'value' => $type,
    ));

    if ($type == 'course') {
        $content .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'itemid',
            'value' => $message->course()->id,
        ));
    } elseif ($type == 'label') {
        $content .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'itemid',
            'value' => $labelid,
        ));
    }
    $content .= html_writer::end_tag('div');

    $refs = $message->references();
    if (!empty($refs)) {
        $content .= $mailoutput->references($refs);
    }
    $content = preg_replace('/^<div>|<\/div>$/', '', $content);
    return array(
        'info' => local_mail_get_info(),
        'html' => $content
    );
}

function local_mail_setlabel($type, $labelid, $labelname, $labelcolor) {
    global $CFG, $USER;

    $error = '';
    $label = local_mail_label::fetch($labelid);
    $colors = local_mail_label::valid_colors();
    $labelname = preg_replace('/\s+/', ' ', $labelname);
    if ($label) {
        $labels = local_mail_label::fetch_user($USER->id);
        $repeatedname = false;
        foreach ($labels as $l) {
            $repeatedname = $repeatedname || (($l->name() === $labelname) and ($l->id() != $labelid));
        }
        if (!$repeatedname) {
            if ($labelname and (!$labelcolor or in_array($labelcolor, $colors))) {
                $label->save($labelname, $labelcolor);
            } else {
                $error = (!$labelname?get_string('erroremptylabelname', 'local_mail'):get_string('errorinvalidcolor', 'local_mail'));
            }
        } else {
            $error = get_string('errorrepeatedlabelname', 'local_mail');
        }
    } else {
        $error = get_string('invalidlabel', 'local_mail');
    }
    return array(
        'msgerror' => $error,
        'info' => '',
        'html' => '',
        'redirect' => $CFG->wwwroot . '/local/mail/view.php?t=' . $type . '&l=' . $labelid
    );
}

function local_mail_newlabel($messages, $labelname, $labelcolor, $data) {
    global $CFG, $USER;

    $error = '';
    $labelname = trim($labelname);
    $labelname = preg_replace('/\s+/', ' ', $labelname);
    $colors = local_mail_label::valid_colors();
    $validcolor = (!$labelcolor or in_array($labelcolor, $colors));
    $labels = local_mail_label::fetch_user($USER->id);
    $repeatedname = false;
    foreach ($labels as $label) {
        $repeatedname = $repeatedname || ($label->name() === $labelname);
    }
    if (!$repeatedname) {
        if (!empty($labelname) and $validcolor) {
            $newlabel = local_mail_label::create($USER->id, $labelname, $labelcolor);
            foreach ($messages as $message) {
                if ($message->viewable($USER->id) and !$message->deleted($USER->id)) {
                    $message->add_label($newlabel);
                }
            }
        } else {
            $error = (empty($labelname)?get_string('erroremptylabelname', 'local_mail'):get_string('errorinvalidcolor', 'local_mail'));
        }
    } else {
        $error = get_string('errorrepeatedlabelname', 'local_mail');
    }

    return array(
        'msgerror' => $error,
        'info' => '',
        'html' => '',
        'redirect' => $CFG->wwwroot . '/local/mail/view.php?' . implode('&', $data)
    );
}

function local_mail_deletelabel($itemid, $data) {
    global $CFG;
    $label = local_mail_label::fetch($itemid);
    $ret = $label->delete($itemid);
    if($ret) {
        $error = '';
    } else {
        $error = get_string('invalidlabel', 'local_mail');
    }
    return array(
         'msgerror' => $error,
         'info' => '',
         'html' => '',
         'redirect' => $CFG->wwwroot . '/local/mail/view.php?' . implode('&', $data)
    );
}

function local_mail_get_info() {
    global $USER;

	$count = local_mail_message::count_menu($USER->id);

    $text = get_string('mymail', 'local_mail');
    if (empty($count->inbox)) {
        $count->root = $text;
    } else {
        $count->root = $text . ' (' . $count->inbox . ')';
    }

    $text = get_string('inbox', 'local_mail');
    if (empty($count->inbox)) {
        $count->inbox = $text;
    } else {
        $count->inbox = $text . ' (' . $count->inbox . ')';
    }

    $text = get_string('drafts', 'local_mail');
    if(empty($count->drafts)) {
        $count->drafts = $text;
    }else{
        $count->drafts = $text . ' (' . $count->drafts . ')';
    }

    $labels = local_mail_label::fetch_user($USER->id);
    if ($labels) {
        foreach ($labels as $label) {
            $text = $label->name();
            if (empty($count->labels[$label->id()])) {
                $count->labels[$label->id()] = $text;
            }else{
                $count->labels[$label->id()] = $text . ' (' . $count->labels[$label->id()] . ')';
            }
        }
    }

    if (!$courses = local_mail_get_my_courses()) {
        return;
    }

    foreach ($courses as $course) {
        $text = $course->shortname;
        if (empty($count->courses[$course->id])) {
            $count->courses[$course->id] = $text;
        } else {
            $count->courses[$course->id] = $text . ' ('. $count->courses[$course->id].')';
        }
    }

    return $count;
}

function local_mail_getrecipients($message, $search, $groupid, $roleid) {
    global $DB, $PAGE, $CFG;

    $participants = array();
    $recipients = array();
    $mailmaxusers = (isset($CFG->maxusersperpage)?$CFG->maxusersperpage:MAIL_MAXUSERS);

    $context = get_context_instance(CONTEXT_COURSE, $message->course()->id, MUST_EXIST);

    if ($message->course()->groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
        $groups = groups_get_user_groups($message->course()->id, $message->sender()->id);
        if (count($groups[0]) == 0) {
                $mailoutput = $PAGE->get_renderer('local_mail');
                return array(
                    'msgerror' => '',
                    'html' => $mailoutput->recipientslist($participants)
                );
        } else {
            if (!in_array($groupid, $groups[0])) {
                $groupid = $groups[0][0];
            }
        }
    }

    list($select, $from, $where, $sort, $params) = local_mail_getsqlrecipients($message->course()->id, $search, $groupid, $roleid);

    $matchcount = $DB->count_records_sql("SELECT COUNT(DISTINCT u.id) $from $where", $params);

    $getid = function ($recipient) {
        return $recipient->id;
    };

    if ($matchcount <= $mailmaxusers) {
        $to = array_map($getid, $message->recipients('to'));
        $cc = array_map($getid, $message->recipients('cc'));
        $bcc = array_map($getid, $message->recipients('bcc'));
        // list of users
        $rs = $DB->get_recordset_sql("$select $from $where $sort", $params);
        foreach ($rs as $rec) {
            if (!array_key_exists($rec->id, $participants)) {
                $rec->role = '';
                if (in_array($rec->id, $to)) {
                    $rec->role = 'to';
                    array_push($recipients, $rec->id);
                } elseif (in_array($rec->id, $cc)) {
                    $rec->role = 'cc';
                    array_push($recipients, $rec->id);
                } elseif (in_array($rec->id, $bcc)) {
                    $rec->role = 'bcc';
                    array_push($recipients, $rec->id);
                }
                $participants[$rec->id] = $rec;
            }
        }
        $rs->close();
    } else {
        $participants = false;
    }
    $mailoutput = $PAGE->get_renderer('local_mail');
    return array(
        'msgerror' => '',
        'info' => $recipients,
        'html' => $mailoutput->recipientslist($participants)
    );
}

function local_mail_updaterecipients($message, $recipients, $roles) {
    global $DB;

    $context = get_context_instance(CONTEXT_COURSE, $message->course()->id, MUST_EXIST);
    $groupid = 0;
    $severalseparategroups = false;

    if ($message->course()->groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
        $groups = groups_get_user_groups($message->course()->id, $message->sender()->id);
        if (count($groups[0]) == 0) {
                return array(
                    'msgerror' => '',
                    'info' => '',
                    'html' => '',
                    'redirect' => 'ok'
                );
        } elseif(count($groups[0]) == 1) {//Only one group
            $groupid = $groups[0][0];
        } else {
            $severalseparategroups = true;//Several groups
        }
    }

    //Make sure recipients ids are integers
    $recipients = clean_param_array($recipients, PARAM_INT);

    foreach ($recipients as $key => $recipid) {
        $roleids[$recipid] = (isset($roles[$key])?clean_param($roles[$key], PARAM_INT):false);
    }

    $participants = array();
    list($select, $from, $where, $sort, $params) = local_mail_getsqlrecipients($message->course()->id, '', $groupid, 0, implode(',', $recipients));
    $rs = $DB->get_recordset_sql("$select $from $where $sort", $params);

    foreach ($rs as $rec) {
        if (!array_key_exists($rec->id, $participants)) {//Avoid duplicated users
            if ($severalseparategroups) {
                $valid = false;
                foreach ($groups[0] as $group) {
                    $valid = $valid || groups_is_member($group, $rec->id);
                }
                if (!$valid) {
                    continue;
                }
            }
            $role = false;
            if ($roleids[$rec->id] === 0) {
                $role = 'to';
            } elseif ($roleids[$rec->id] === 1) {
                $role = 'cc';
            } elseif ($roleids[$rec->id] === 2) {
                $role = 'bcc';
            }
            if ($message->has_recipient($rec->id)) {
                $message->remove_recipient($rec->id);
            }
            if ($role) {
                $message->add_recipient($role, $rec->id);
            }
            $participants[$rec->id] = true;
        }
    }

    $rs->close();
    return array(
        'msgerror' => '',
        'info' => '',
        'html' => '',
        'redirect' => 'ok'
    );
}

function local_mail_getsqlrecipients($courseid, $search, $groupid, $roleid, $recipients = false){
    global $CFG, $USER, $DB;

    $context = get_context_instance(CONTEXT_COURSE, $courseid, MUST_EXIST);

    list($esql, $params) = get_enrolled_sql($context, NULL, $groupid, true);
    $joins = array("FROM {user} u");
    $wheres = array();

    $extrasql = get_extra_user_fields_sql($context, 'u', '', array(
            'id', 'firstname', 'lastname'));
    $select = "SELECT u.id, u.firstname, u.lastname, u.picture, u.imagealt, u.email, ra.roleid$extrasql";
    $joins[] = "JOIN ($esql) e ON e.id = u.id";
    $joins[] = 'LEFT JOIN {role_assignments} ra ON (ra.userid = u.id AND ra.contextid '
            . get_related_contexts_string($context) . ')'
            . ' LEFT JOIN {role} r ON r.id = ra.roleid';

    // performance hacks - we preload user contexts together with accounts
    list($ccselect, $ccjoin) = context_instance_preload_sql('u.id', CONTEXT_USER, 'ctx');
    $select .= $ccselect;
    $joins[] = $ccjoin;

    $from = implode("\n", $joins);

    if (!empty($search)) {
        $fullname = $DB->sql_fullname('u.firstname','u.lastname');
        $wheres[] = "(". $DB->sql_like($fullname, ':search1', false, false) .") ";
        $params['search1'] = "%$search%";
    }

    $from = implode("\n", $joins);
    $wheres[] = 'u.id <> :guestid AND u.deleted = 0 AND u.confirmed = 1 AND u.id <> :userid';
    if ($roleid != 0) {
        $wheres[] = 'r.id = :roleid';
        $params['roleid'] = $roleid;
    }

    if ($recipients) {
        $wheres[] = 'u.id IN ('.preg_replace('/^,|,$/', '', $recipients).')';
    }

    $params['userid'] = $USER->id;
    $params['guestid'] = $CFG->siteguest;
    $where = "WHERE " . implode(" AND ", $wheres);

    $sort = 'ORDER BY u.lastname ASC, u.firstname ASC';

    return array($select, $from, $where, $sort, $params);
}

function local_mail_searchmessages($type, $itemid, $query, $offset = false, $perpage = false) {
    global $USER, $PAGE;

    $prev = $next = false;
    $date = $nummsgs = '';
    $url = new moodle_url('/local/mail/view.php', array('t' => $type));
    $PAGE->set_url($url);
    $mailoutput = $PAGE->get_renderer('local_mail');
    if (!empty($query['time'])) {
        $date = $query['time'];
        $time = explode(',', $query['time']);
        if (count($time) == 3) {
            $query['time'] = make_timestamp($time[0], $time[1], $time[2], 23, 59, 59);
        } else {
            $query['time'] = '';
        }
    }
    $query['before'] = ($query['before'] == 0?'':$query['before']);
    $query['after'] = ($query['after'] == 0?'':$query['after']);
    $mailpagesize = $query['limit'];
    $query['limit'] += 1;
    if ($perpage) {
        $query['before'] = ($query['perpageid'] == 0?'':$query['perpageid']);
        $query['after'] = '';
    }
    $messages = local_mail_message::search_index($USER->id, $type, $itemid, $query);
    $nummsgs = count($messages);
    if ($nummsgs == ($query['limit'])) {
        if (!empty($query['after'])) {
            $query['perpageid'] = $messages[0]->id();
            $messages = array_slice($messages, 1, count($messages));
            $prev = true;
        } else {
            $messages = array_slice($messages, 0, count($messages)-1);
            $next = true;
        }
    } elseif(!empty($query['after']) and $nummsgs < ($query['limit'])) {
        $query['limit'] -= $nummsgs;
        $query['after'] = '';
        $query['before'] = (isset($messages[$nummsgs-1])?$messages[$nummsgs-1]->id():'');
        $newmessages = local_mail_message::search_index($USER->id, $type, $itemid, $query);
        if (count($newmessages) == ($query['limit'])) {
            $newmessages = array_slice($newmessages, 0, count($newmessages)-1);
            $next = true;
        }
        $query['before'] = '';
        $messages = array_merge($messages, $newmessages);
    }
    $content = local_mail_print_messages($itemid, $type, 0, $messages, false);
    $prev = ($prev or !empty($query['before']));
    $next = ($next or !empty($query['after']));
    if (!$prev) {
        $query['perpageid'] = 0;
    }
    $data = array(
        'query' => $query['pattern'],
        'unread' => !empty($query['unread']),
        'date' => $date,
        'prev' => $prev,
        'next' => $next,
        'idafter' => (!empty($query['after'])?$query['after']:false),
        'idbefore' => (!empty($query['before'])?$query['before']:false),
        'perpageid' => $query['perpageid']
    );
    return array(
        'info' => '',
        'html' => $content,
        'search' => $data,
        'perpage' => ($offset !== false?$mailoutput->perpage($offset, $mailpagesize):'')
    );
}

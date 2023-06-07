<?php

function _view_data($marcField, $position = 'a') {
    if (isset($marcField['subfields'])) {
        foreach ($marcField['subfields'] as $field) {
             switch ($field['code']) {
                case $position:
                    return $field['data'];
                    break;
             }
        }
    }
    return '';
}

function printWithDuplicateDataValues($array, $url) {
    $return = '';
    foreach ($array as $item) {
        $add ="";
        if ($item['tag']=="084") { $add = "&type=Classification"; }
        if ($item['tag']=="655") { $add = "&type=Subject"; }
        foreach ($item['subfields'] as $subfield) {
            if (($subfield['code'] == 'a' && $subfield['data'] != '0') || ($subfield['code'] != '0' && $subfield['code'] != '2')) {
                if (strlen($url) > 3) {
                    $return .= "<a href='" . $url . "?lookfor=%22" . $subfield['data'] . "%22".$add."'>" . $subfield['data'] . "</a>&nbsp; ";
                } else {
                    $return .= $subfield['data'] . "&nbsp;";
                }
            }
        }
    }
    return $return;
}

function printDataValues($array,$url) {
    $return = '';
    foreach ($array['subfields'] as $subfield) {
        $subfield['data'] = str_replace(" --","",$subfield['data']);
        if ($subfield['code']!="2") {
             $return .= "<a href='".$url."?lookfor=%22".$subfield['data']."%22'>".$subfield['data']."</a>&nbsp; ";
        }
    }
    return $return;
}


function echoTableData($label, $value) {
    if (strlen($value) > 0) {
        echo '<tr>';
        echo '<th width="150px;">' . $label . ':</th>';
        echo '<td>' . $value . '</td>';
        echo '</tr>';
    }
}

?>
<?php

/**
 * This file is run via exec (http://php.net/function.exec)
 */
define('WP_USE_THEMES', false);
require( dirname(__FILE__) . '/../../../wp-blog-header.php');

$entry_id = $argv[1];
$form_id = $argv[2];

$types = array(
    'smoochie' => array(
        'name' => 'Smoochies',
        'color' => 'red'
    ),
    'large' => array(
        'name' => 'Large Bars',
        'color' => 'green'
    ),
    'small' => array(
        'name' => 'Small Bars',
        'color' => 'yellow'
    ),
    'lotion' => array(
        'name' => 'Lotion',
        'color' => 'blue'
    )
);

/* Get options from database */
$app_key = get_option("gf_trello_key");
$app_secret = get_option("gf_trello_secret");
$app_board = get_option("gf_trello_board");
$app_list = get_option("gf_trello_list");

$authenticated = get_option("gf_trello_access_token");

/* include the trello api */
include 'trello-api/Trello.php';

//We need to be authenticated and have a list to update
if ($authenticated && $app_list) {
    //Get the Trello API
    $trello = new Trello($app_key, null, $authenticated);

    //Initialize Card Name
    $cardName = false;
    //Initialze Inventory Array
    $inventory = array_fill_keys ($types, array());
    //Initialize Empty array for label colors
    $labels = array();

    $entry = RGFormsModel::get_lead($entry_id);
    $form = RGFormsModel::get_form_meta($form_id);

    $comment = "";
    //Loop through all the fields on the form
    foreach ($form['fields'] as $field) {
        //If this is the shop name it will become the card name
        if ($field['adminLabel'] == GF_TRELLO_CARD_NAME_FIELD || $field['label'] == GF_TRELLO_CARD_NAME_FIELD) {
            $cardName = $entry[$field['id']];
            continue;
        }

        //Explode the Admin Label on the first space
        $blast = explode(" ", $field['adminLabel'], 2);
        //This will be the type large bar | small bar | smoochie | lotion
        $type = (isset($blast[0]) && $blast[0]) ? strtolower($blast[0]) : false;
        //This is the short name of the item
        $shortName = (isset($blast[1]) && $blast[1]) ? strtoupper($blast[1]) : false;
        //Get the amount filled in
        $amount = $entry[$field['id']];

        //Do we have all the information? Then we must be an inventory entry
        if($type && $shortName && $amount && isset($types[$type]))  {
            //Translate the type name to the checklist name
            $typeName = $types[$type]['name'];
            $inventory[$typeName][$shortName] = $amount;
            $labels[] = $types[$type]['color'];
        } elseif ($entry[$field['id']] && $field['enableCalculation'] == 0) {
            //$label = $field['label'];
            $value = $entry[$field['id']];
            $comment .= $value.PHP_EOL;

        }
    }

    //Create our card
    $card = $trello->post('cards', array(
        'name' => strtoupper($cardName) . " - Generating",
        'idList' => $app_list,
        'labels' => $labels,
        'desc' => $comment
    ));

    /*$trello->post('cards/'. $card->id . '/actions/comments', array(
        'text' => $comment
    ));*/

    //Loop thru the labels and add them to the card
    foreach ($labels as $color) {
        $trello->post('cards/' . $card->id . '/labels', array(
            'value' => $color
        ));
    }

    //Loop thru the inventory types creating our checklists
    foreach($inventory as $type => $counts) {
        //Do we have any of this type of inventory?
        if (count($counts)) {
            //Get the count, this goes in the name of the checklist
            $sum = array_sum($counts);

            //Create our checklist
            $checklist = $trello->post('cards/' . $card->id . '/checklists', array(
                'name' => strtoupper($type) . " " . $sum
            ));

            //Loop through the inventory and add the checklist items to the checklist
            foreach ($counts as $name => $count) {
                $trello->post('checklist/' . $checklist->id .'/checkItems', array(
                    'name' => $name . " " . $count
                ));
            }
        }
    }

    $trello->put('cards/' . $card->id . '/name', array(
        'value' => strtoupper($cardName)
    ));
}
<?php

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Collections\NotesCollection;
use AmoCRM\Models\NoteType\CommonNote;
use AmoCRM\Filters\EventsFilter;

$leadNotesService = $apiClient->notes(EntityTypesInterface::LEADS);
$notesCollection = new NotesCollection();

function getEventsOnLeadUpdate(AmoCRMApiClient $apiClient, int $leadUpdatedAt): array
{
    $eventFilter = new EventsFilter();
    //$eventFilter->setEntityIds([3025663]);//не работает фильтр
    $eventFilter->setCreatedAt([$leadUpdatedAt]);

    $eventService = $apiClient->events();
    $events = $eventService->get($eventFilter);
    if ($events->isEmpty()) {
        throw new Exception('Events are empty');
    }
    return $events->toArray();
}

function createNoteFromLeadUpdate(array $event, array $leadData, array $fieldLabels): CommonNote
{
    if ((int) $event['entity_id'] !==  (int) $leadData['id']) {
        throw new Exception('Wrong lead id');
    }

    // Get the field value before and after the change
    $before = $event['value_before'][0];
    $after = $event['value_after'][0];

    // Initialize variables to hold the field name and value
    $fieldName = null;
    $beforeValue = null;
    $afterValue = null;

    // Loop through the before and after arrays to find the changed fields dynamically
    foreach ($before as $key => $value) {
        // Check if the key exists in both before and after arrays
        if (isset($after[$key]) && is_array($before[$key]) && is_array($after[$key])) {
            // Loop through the nested arrays to find the changed field
            foreach ($before[$key] as $nestedKey => $nestedValue) {
                if (isset($after[$key][$nestedKey]) && $before[$key][$nestedKey] !== $after[$key][$nestedKey]) {
                    // Field has changed, set the field name and values
                    $fieldName = $nestedKey;
                    $beforeValue = $before[$key][$nestedKey];
                    $afterValue = $after[$key][$nestedKey];
                    break 2; // Stop looping once a changed field is found
                }
            }
        }
    }

    // If a changed field was found, compose the note text
    if ($fieldName === null) {
        throw new Exception('Field name not found');
    }
    // Check if the field label exists in the mapping array
    $fieldLabel = $fieldLabels[$fieldName] ?? $fieldName;
    $text = "$fieldLabel changed from $beforeValue to $afterValue" . PHP_EOL;
    // Create and add the note
    $note = new CommonNote();
    $note->setEntityId($event['entity_id']);
    $note->setText($text);

    myLog(['note' => $note]);
    return $note;
}

if (isset($_REQUEST['leads']['update'])) {
    sleep(5); //события создаются с определенной задержкой, если сразу запросить, не достаются

    foreach ($_REQUEST['leads']['update'] as $leadData) {

        $events = getEventsOnLeadUpdate($apiClient, $leadData['updated_at']);

        // Define a mapping of field names to their human-readable labels
        $fieldLabels = [
            'responsible_user' => 'Responsible user',
            'sale' => 'Sale',
        ];

        foreach ($events as $event) {
            try {
                $note = createNoteFromLeadUpdate($event, $leadData, $fieldLabels);
                $notesCollection->add($note);
            } catch(Exception) {
                myLog($e);
                continue;
            }
        }
    }

    if ($notesCollection->isEmpty()) {
        throw new Exception('notes collection is empty');
    }

    $leadNotesService = $apiClient->notes(EntityTypesInterface::LEADS);
    $notesCollection = $leadNotesService->add($notesCollection);
} elseif (isset($_REQUEST['leads']['add'])) {
    foreach ($_REQUEST['leads']['add'] as $lead) {
        $usersService = $apiClient->users();
        $responsibleUser = $usersService->getOne($lead['responsible_user_id']);
        $text = $lead['name']
            . PHP_EOL
            . $responsibleUser->getName()
            . PHP_EOL
            . date('Y-m-d H:i:s', $lead['created_at']);

        $note = new CommonNote();
        $note->setEntityId($lead['id']);
        $note->setText($text);
        $notesCollection->add($note);
    }
    try {
        $notesCollection = $leadNotesService->add($notesCollection);
    } catch (AmoCRMApiException $e) {
        printError($e);
        die;
    }
}

function composeAddNote($usersService, $lead)
{
    $responsibleUser = $usersService->getOne($lead['responsible_user_id']);
    $text = $lead['name']
        . PHP_EOL
        . $responsibleUser->getName()
        . PHP_EOL
        . date('Y-m-d H:i:s', $lead['created_at']);

    $note = new CommonNote();
    $note->setEntityId($lead['id']);
    $note->setText($text);
    return $note;
}

function sendNotes($leadNotesService, $notesCollection)
{
    try {
        $notesCollection = $leadNotesService->add($notesCollection);
    } catch (AmoCRMApiException $e) {
        printError($e);
        die;
    }
}

<?php

namespace src;

use AmoCRM\Client\AmoCRMApiClient;
use Exception;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Collections\NotesCollection;
use AmoCRM\Models\NoteType\CommonNote;
use AmoCRM\Filters\EventsFilter;

final class LeadHook
{
    public function __construct(private AmoCRMApiClient $apiClient)
    {
    }

    public function addNoteOnUpdate(array $request): void
    {
        myLog('in addNoteOnUpdate');
        $leadNotesService = $this->apiClient->notes(EntityTypesInterface::LEADS);
        $notesCollection = new NotesCollection();
        sleep(5); //события создаются с определенной задержкой, если сразу запросить, не достаются
        foreach ($request['leads']['update'] as $leadData) {

            $eventFilter = new EventsFilter();
            //$eventFilter->setEntityIds([3025663]);//не работает фильтр
            $eventFilter->setCreatedAt([$leadData['updated_at']]);

            $eventService = $this->apiClient->events();
            $events = $eventService->get($eventFilter);
            if ($events->isEmpty()) {
                throw new Exception('Events are empty');
            }
            $events =  $events->toArray();

            // Define a mapping of field names to their human-readable labels
            $fieldLabels = [
                'responsible_user' => 'Responsible user',
                'sale' => 'Sale',
            ];

            foreach ($events as $event) {

                if ((int) $event['entity_id'] !==  (int) $leadData['id']) {
                    continue;
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
                    continue;
                }
                // Check if the field label exists in the mapping array
                $fieldLabel = $fieldLabels[$fieldName] ?? $fieldName;
                $text = "$fieldLabel changed from $beforeValue to $afterValue" . PHP_EOL;
                // Create and add the note
                $note = new CommonNote();
                $note->setEntityId($event['entity_id']);
                $note->setText($text);

                myLog(['note' => $note]);

                $notesCollection->add($note);
            }
        }

        if ($notesCollection->isEmpty()) {
            throw new Exception('notes collection is empty');
        }

        $leadNotesService = $this->apiClient->notes(EntityTypesInterface::LEADS);
        $notesCollection = $leadNotesService->add($notesCollection);
    }

    public function addNoteOnCreate()
    {
        $leadNotesService = $this->apiClient->notes(EntityTypesInterface::LEADS);
        $notesCollection = new NotesCollection();

        foreach ($_REQUEST['leads']['update'] as $lead) {
            $note = new CommonNote();
            $note->setEntityId($lead['id']);
            $usersService = $this->apiClient->users();
            $responsibleUser = $usersService->getOne($lead['responsible_user_id']);
            $text = $lead['name']
                . PHP_EOL
                . $responsibleUser->getName()
                . PHP_EOL
                . date('Y-m-d H:i:s', $lead['created_at']);

            $note->setText($text);
            $notesCollection->add($note);
        }
        try {
            $leadNotesService->add($notesCollection);
        } catch (AmoCRMApiException $e) {
            printError($e);
            die;
        }
    }
}

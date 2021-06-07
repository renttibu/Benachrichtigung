<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Benachrichtigung/tree/main/Benachrichtigung
 */

declare(strict_types=1);

include_once __DIR__ . '/helper/autoload.php';

class Benachrichtigung extends IPSModule
{
    // Helper
    use BN_backupRestore;
    use BN_notification;
    use BN_triggerVariable;

    // Constants
    private const LIBRARY_GUID = '{ED3F80BE-52B6-05C5-D52E-A07784A4B5CF}';
    private const MODULE_NAME = 'Benachrichtigung';
    private const MODULE_PREFIX = 'UBBN';
    private const WEBFRONT_MODULE_GUID = '{3565B1F2-8F7B-4311-A4B6-1BF1D868F39E}';
    private const MAILER_MODULE_GUID = '{E43C3C36-8402-6B6D-2699-D870FBC216EF}';
    private const NEXXTMOBILE_SMS_MODULE_GUID = '{7E6DBE40-4438-ABB7-7EE0-93BC4F1AF0CE}';
    private const SIPGATE_SMS_MODULE_GUID = '{965ABB3F-B4EE-7F9F-1E5E-ED386219EF7C}';
    private const TELEGRAM_BOT_MODULE_GUID = '{32464EBD-4CCC-6174-4031-5AA374F7CD8D}';

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Properties
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        $this->RegisterPropertyString('TriggerVariables', '[]');
        $this->RegisterPropertyString('WebFrontNotification', '[]');
        $this->RegisterPropertyString('WebFrontPushNotification', '[]');
        $this->RegisterPropertyString('Mailer', '[]');
        $this->RegisterPropertyString('NexxtMobile', '[]');
        $this->RegisterPropertyString('Sipgate', '[]');
        $this->RegisterPropertyString('Telegram', '[]');

        // Variables
        $id = @$this->GetIDForIdent('Notification');
        $this->RegisterVariableBoolean('Notification', 'Benachrichtigung', '~Switch', 10);
        $this->EnableAction('Notification');
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('Notification'), 'Mobile');
            $this->SetValue('Notification', true);
        }
    }

    public function ApplyChanges()
    {
        // Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        // Never delete this line!
        parent::ApplyChanges();

        // Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        // Delete all references
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        // Delete all registrations
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                if ($message == VM_UPDATE) {
                    $this->UnregisterMessage($senderID, VM_UPDATE);
                }
            }
        }

        if (!$this->CheckMaintenanceMode()) {
            $this->SendDebug(__FUNCTION__, 'Referenzen und Nachrichten werden registriert.', 0);
            // Register references and update messages
            foreach (json_decode($this->ReadPropertyString('TriggerVariables')) as $variable) {
                if ($variable->Use) {
                    $id = $variable->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $this->RegisterReference($id);
                        $this->RegisterMessage($id, VM_UPDATE);
                    }
                }
            }
        }
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug('MessageSink', 'Message from SenderID ' . $SenderID . ' with Message ' . $Message . "\r\n Data: " . print_r($Data, true), 0);
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            case VM_UPDATE:

                //$Data[0] = actual value
                //$Data[1] = value changed
                //$Data[2] = last value
                //$Data[3] = timestamp actual value
                //$Data[4] = timestamp value changed
                //$Data[5] = timestamp last value

                if ($this->CheckMaintenanceMode()) {
                    return;
                }

                if (!$this->GetValue('Notification')) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, die Benachrichtigungsfunktion ist ausgeschaltet!', 0);
                    return;
                }

                // Check trigger variable
                $valueChanged = 'false';
                if ($Data[1]) {
                    $valueChanged = 'true';
                }
                $scriptText = self::MODULE_PREFIX . '_CheckTriggerVariable(' . $this->InstanceID . ', ' . $SenderID . ', ' . $valueChanged . ');';
                IPS_RunScriptText($scriptText);
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $form = [];

        #################### Elements

        ########## Functions

        ##### Functions panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Wartungsmodus',
            'items'   => [
                [
                    'type'    => 'CheckBox',
                    'name'    => 'MaintenanceMode',
                    'caption' => 'Wartungsmodus'
                ]
            ]
        ];

        ########## Trigger variables

        $triggerVariables = [];
        foreach (json_decode($this->ReadPropertyString('TriggerVariables')) as $variable) {
            $rowColor = '#FFC0C0'; # red
            $id = $variable->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#DFDFDF'; # grey
                if ($variable->Use) {
                    $rowColor = '#C0FFC0'; # light green
                }
            }
            $triggerVariables[] = ['rowColor' => $rowColor];
        }

        ##### Trigger variables panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Auslöser',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'TriggerVariables',
                    'caption'  => '',
                    'rowCount' => 10,
                    'add'      => true,
                    'delete'   => true,
                    'sort'     => [
                        'column'    => 'ID',
                        'direction' => 'ascending'
                    ],
                    'columns' => [
                        [
                            'name'    => 'Use',
                            'caption' => 'Aktiviert',
                            'width'   => '100px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'Description',
                            'caption' => 'Beschreibung',
                            'width'   => '200px',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'name'    => 'TriggerVariableSpacer',
                            'caption' => ' ',
                            'width'   => '150px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type' => 'Label'
                            ]
                        ],
                        [
                            'name'    => 'ID',
                            'caption' => 'Auslösende Variable',
                            'width'   => '350px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $TriggerVariables["ID"], "TriggerVariableConfigurationButton", 0);',
                            'edit'    => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'name'    => 'Info',
                            'caption' => 'Info',
                            'width'   => '150px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type'    => 'Button',
                                'onClick' => self::MODULE_PREFIX . '_ShowVariableDetails($id, $ID);'
                            ]
                        ],
                        [
                            'name'    => 'TriggerType',
                            'caption' => 'Auslöseart',
                            'width'   => '280px',
                            'add'     => 6,
                            'edit'    => [
                                'type'    => 'Select',
                                'options' => [
                                    [
                                        'caption' => 'Bei Änderung',
                                        'value'   => 0
                                    ],
                                    [
                                        'caption' => 'Bei Aktualisierung',
                                        'value'   => 1
                                    ],
                                    [
                                        'caption' => 'Bei Grenzunterschreitung (einmalig)',
                                        'value'   => 2
                                    ],
                                    [
                                        'caption' => 'Bei Grenzunterschreitung (mehrmalig)',
                                        'value'   => 3
                                    ],
                                    [
                                        'caption' => 'Bei Grenzüberschreitung (einmalig)',
                                        'value'   => 4
                                    ],
                                    [
                                        'caption' => 'Bei Grenzüberschreitung (mehrmalig)',
                                        'value'   => 5
                                    ],
                                    [
                                        'caption' => 'Bei bestimmtem Wert (einmalig)',
                                        'value'   => 6
                                    ],
                                    [
                                        'caption' => 'Bei bestimmtem Wert (mehrmalig)',
                                        'value'   => 7
                                    ]
                                ]
                            ]
                        ],
                        [
                            'name'    => 'TriggerValue',
                            'caption' => 'Auslösewert',
                            'width'   => '160px',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'name'    => 'SecondVariableSpacer',
                            'caption' => ' ',
                            'width'   => '150px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type' => 'Label'
                            ]
                        ],
                        [
                            'name'    => 'SecondVariable',
                            'caption' => 'Weitere Variable',
                            'width'   => '350px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $TriggerVariables["SecondVariable"], "TriggerVariableConfigurationButton", 0);',
                            'edit'    => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'name'    => 'SecondVariableInfo',
                            'caption' => 'Info',
                            'width'   => '150px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type'    => 'Button',
                                'onClick' => self::MODULE_PREFIX . '_ShowVariableDetails($id, $SecondVariable);'
                            ]
                        ],
                        [
                            'name'    => 'SecondVariableValue',
                            'caption' => 'Variablenwert',
                            'width'   => '160px',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'name'    => 'MessageSpacer',
                            'caption' => ' ',
                            'width'   => '150px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type' => 'Label'
                            ]
                        ],
                        [
                            'name'    => 'Title',
                            'caption' => 'Titel',
                            'width'   => '300px',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'name'    => 'TriggeringDetector',
                            'caption' => 'Auslösender Melder (%1$s)',
                            'width'   => '350px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $TriggerVariables["TriggeringDetector"], "TriggerVariableConfigurationButton", 0);',
                            'edit'    => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'name'    => 'MessageText',
                            'caption' => 'Benachrichtigungstext',
                            'width'   => '400px',
                            'add'     => '%1$s',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'name'    => 'UseTimestamp',
                            'caption' => 'Zeitstempel',
                            'width'   => '120px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'WebFrontNotificationSpacer',
                            'caption' => ' ',
                            'width'   => '150px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type' => 'Label'
                            ]
                        ],

                        [
                            'name'    => 'UseWebFrontNotification',
                            'caption' => 'WebFront Notification',
                            'width'   => '190px',
                            'add'     => false,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'WebFrontNotificationTextSymbol',
                            'caption' => 'Textsymbol',
                            'width'   => '120px',
                            'add'     => '',
                            'edit'    => [
                                'type'    => 'Select',
                                'options' => [
                                    [
                                        'caption' => 'Kein Symbol',
                                        'value'   => ''
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udd34"'), # red
                                        'value'   => '"\ud83d\udd34"'
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udfe0"'), # orange
                                        'value'   => '"\ud83d\udfe0"'
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udfe1"'), # yellow
                                        'value'   => '"\ud83d\udfe1"'
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udfe2"'), # green
                                        'value'   => '"\ud83d\udfe2"'
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udd35"'), # blue
                                        'value'   => '"\ud83d\udd35"'
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udfe3"'), # violett
                                        'value'   => '"\ud83d\udfe3"'
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udfe4"'), # brown
                                        'value'   => '"\ud83d\udfe4"'
                                    ],
                                    [
                                        'caption' => json_decode('"\u26ab"'), # black
                                        'value'   => '"\u26ab"'
                                    ],
                                    [
                                        'caption' => json_decode('"\u26aa"'), # white
                                        'value'   => '"\u26aa"'
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udd57"'), # clock
                                        'value'   => '"\ud83d\udd57"'
                                    ],
                                    [
                                        'caption' => json_decode('"\u2705"'), # # white_check_mark
                                        'value'   => '"\u2705"'
                                    ],
                                    [
                                        'caption' => json_decode('"\u26a0\ufe0f"'), # warning
                                        'value'   => '"\u26a0\ufe0f"'
                                    ],
                                    [
                                        'caption' => json_decode('"\u2757"'), # red exclamation mark
                                        'value'   => '"\u2757"'
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udeab"'), # no entry sign
                                        'value'   => '"\ud83d\udeab"'
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udd25"'), # flame
                                        'value'   => '"\ud83d\udd25"'
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udca7"'), # droplet
                                        'value'   => '"\ud83d\udca7"'
                                    ],
                                    [
                                        'caption' => json_decode('"\uD83E\uDD77"'), # nina
                                        'value'   => '"\uD83E\uDD77"'
                                    ]
                                ]
                            ]
                        ],
                        [
                            'name'    => 'WebFrontNotificationIcon',
                            'caption' => 'Icon',
                            'width'   => '120px',
                            'add'     => 'Information',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'name'    => 'WebFrontNotificationDisplayDuration',
                            'caption' => 'Anzeigedauer',
                            'width'   => '120px',
                            'add'     => 0,
                            'edit'    => [
                                'type'   => 'NumberSpinner',
                                'suffix' => ' Sekunden'
                            ]
                        ],
                        [
                            'name'    => 'WebFrontPushNotificationSpacer',
                            'caption' => ' ',
                            'width'   => '150px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type' => 'Label'
                            ]
                        ],
                        [
                            'name'    => 'UseWebFrontPushNotification',
                            'caption' => 'WebFront Push Notification',
                            'width'   => '240px',
                            'add'     => false,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'WebFrontPushNotificationTextSymbol',
                            'caption' => 'Textsymbol',
                            'width'   => '120px',
                            'add'     => '',
                            'edit'    => [
                                'type'    => 'Select',
                                'options' => [
                                    [
                                        'caption' => 'Kein Symbol',
                                        'value'   => ''
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udd34"'), # red
                                        'value'   => '"\ud83d\udd34"'
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udfe0"'), # orange
                                        'value'   => '"\ud83d\udfe0"'
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udfe1"'), # yellow
                                        'value'   => '"\ud83d\udfe1"'
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udfe2"'), # green
                                        'value'   => '"\ud83d\udfe2"'
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udd35"'), # blue
                                        'value'   => '"\ud83d\udd35"'
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udfe3"'), # violett
                                        'value'   => '"\ud83d\udfe3"'
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udfe4"'), # brown
                                        'value'   => '"\ud83d\udfe4"'
                                    ],
                                    [
                                        'caption' => json_decode('"\u26ab"'), # black
                                        'value'   => '"\u26ab"'
                                    ],
                                    [
                                        'caption' => json_decode('"\u26aa"'), # white
                                        'value'   => '"\u26aa"'
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udd57"'), # clock
                                        'value'   => '"\ud83d\udd57"'
                                    ],
                                    [
                                        'caption' => json_decode('"\u2705"'), # # white_check_mark
                                        'value'   => '"\u2705"'
                                    ],
                                    [
                                        'caption' => json_decode('"\u26a0\ufe0f"'), # warning
                                        'value'   => '"\u26a0\ufe0f"'
                                    ],
                                    [
                                        'caption' => json_decode('"\u2757"'), # red exclamation mark
                                        'value'   => '"\u2757"'
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udeab"'), # no entry sign
                                        'value'   => '"\ud83d\udeab"'
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udd25"'), # flame
                                        'value'   => '"\ud83d\udd25"'
                                    ],
                                    [
                                        'caption' => json_decode('"\ud83d\udca7"'), # droplet
                                        'value'   => '"\ud83d\udca7"'
                                    ],
                                    [
                                        'caption' => json_decode('"\uD83E\uDD77"'), # nina
                                        'value'   => '"\uD83E\uDD77"'
                                    ]
                                ]
                            ]
                        ],
                        [
                            'name'    => 'WebFrontPushNotificationSound',
                            'caption' => 'Sound',
                            'width'   => '120px',
                            'add'     => '',
                            'edit'    => [
                                'type'    => 'Select',
                                'options' => [
                                    [
                                        'caption' => 'Standard',
                                        'value'   => ''
                                    ],
                                    [
                                        'caption' => 'Alarm',
                                        'value'   => 'alarm'
                                    ],
                                    [
                                        'caption' => 'Bell',
                                        'value'   => 'bell'
                                    ],
                                    [
                                        'caption' => 'Boom',
                                        'value'   => 'boom'
                                    ],
                                    [
                                        'caption' => 'Buzzer',
                                        'value'   => 'buzzer'
                                    ],
                                    [
                                        'caption' => 'Connected',
                                        'value'   => 'connected'
                                    ],
                                    [
                                        'caption' => 'Dark',
                                        'value'   => 'dark'
                                    ],
                                    [
                                        'caption' => 'Digital',
                                        'value'   => 'digital'
                                    ],
                                    [
                                        'caption' => 'Drums',
                                        'value'   => 'drums'
                                    ],
                                    [
                                        'caption' => 'Duck',
                                        'value'   => 'duck'
                                    ],
                                    [
                                        'caption' => 'Full',
                                        'value'   => 'full'
                                    ],
                                    [
                                        'caption' => 'Happy',
                                        'value'   => 'happy'
                                    ],
                                    [
                                        'caption' => 'Horn',
                                        'value'   => 'horn'
                                    ],
                                    [
                                        'caption' => 'Inception',
                                        'value'   => 'inception'
                                    ],
                                    [
                                        'caption' => 'Kazoo',
                                        'value'   => 'kazoo'
                                    ],
                                    [
                                        'caption' => 'Roll',
                                        'value'   => 'roll'
                                    ],
                                    [
                                        'caption' => 'Siren',
                                        'value'   => 'siren'
                                    ],
                                    [
                                        'caption' => 'Space',
                                        'value'   => 'space'
                                    ],
                                    [
                                        'caption' => 'Trickling',
                                        'value'   => 'trickling'
                                    ],
                                    [
                                        'caption' => 'Turn',
                                        'value'   => 'turn'
                                    ]
                                ]
                            ]
                        ],
                        [
                            'name'    => 'WebFrontPushNotificationTargetID',
                            'caption' => 'Zielscript',
                            'width'   => '350px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $TriggerVariables["WebFrontPushNotificationTargetID"], "TriggerVariableConfigurationButton", 0);',
                            'edit'    => [
                                'type' => 'SelectScript'
                            ]
                        ],
                        [
                            'name'    => 'MailSpacer',
                            'caption' => ' ',
                            'width'   => '150px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type' => 'Label'
                            ]
                        ],
                        [
                            'name'    => 'UseMailer',
                            'caption' => 'E-Mail',
                            'width'   => '100px',
                            'add'     => false,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'Subject',
                            'caption' => 'Betreff',
                            'width'   => '300px',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'name'    => 'SMSSpacer',
                            'caption' => ' ',
                            'width'   => '150px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type' => 'Label'
                            ]
                        ],
                        [
                            'name'    => 'UseSMS',
                            'caption' => 'SMS',
                            'width'   => '100px',
                            'add'     => false,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'TelegramSpacer',
                            'caption' => ' ',
                            'width'   => '150px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type' => 'Label'
                            ]
                        ],
                        [
                            'name'    => 'UseTelegram',
                            'caption' => 'Telegram',
                            'width'   => '100px',
                            'add'     => false,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ]
                    ],
                    'values' => $triggerVariables,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'name'     => 'TriggerVariableConfigurationButton',
                    'caption'  => 'Bearbeiten',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ]
            ]
        ];

        ########## WebFront

        // WebFront (Notification)
        $webFrontNotificationValues = [];
        foreach (json_decode($this->ReadPropertyString('WebFrontNotification')) as $element) {
            $rowColor = '#FFC0C0'; # red
            $id = $element->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#C0FFC0'; # light green
                if (!$element->Use) {
                    $rowColor = '#DFDFDF'; # grey
                }
            }
            $webFrontNotificationValues[] = ['rowColor' => $rowColor];
        }

        // WebFront (Push notifications)
        $webFrontPushNotificationValues = [];
        foreach (json_decode($this->ReadPropertyString('WebFrontPushNotification')) as $element) {
            $rowColor = '#FFC0C0'; # red
            $id = $element->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#C0FFC0'; # light green
                if (!$element->Use) {
                    $rowColor = '#DFDFDF'; # grey
                }
            }
            $webFrontPushNotificationValues[] = ['rowColor' => $rowColor];
        }

        ##### WebFront panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'WebFront',
            'items'   => [
                // WebFront (Notification)
                [
                    'type'     => 'List',
                    'name'     => 'WebFrontNotification',
                    'caption'  => 'Notification',
                    'rowCount' => 5,
                    'add'      => true,
                    'delete'   => true,
                    'sort'     => [
                        'column'    => 'ID',
                        'direction' => 'ascending'
                    ],
                    'columns' => [
                        [
                            'name'    => 'Use',
                            'caption' => 'Aktiviert',
                            'width'   => '100px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'ID',
                            'caption' => 'WebFront',
                            'width'   => 'auto',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $WebFrontNotification["ID"], "WebFrontNotificationConfigurationButton", 1);',
                            'edit'    => [
                                'type'     => 'SelectModule',
                                'moduleID' => self::WEBFRONT_MODULE_GUID
                            ]
                        ]
                    ],
                    'values' => $webFrontNotificationValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'name'     => 'WebFrontNotificationConfigurationButton',
                    'caption'  => 'Bearbeiten',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ],
                // WebFront (Push notification)
                [
                    'type'     => 'List',
                    'name'     => 'WebFrontPushNotification',
                    'caption'  => 'Push Notification',
                    'rowCount' => 5,
                    'add'      => true,
                    'delete'   => true,
                    'sort'     => [
                        'column'    => 'ID',
                        'direction' => 'ascending'
                    ],
                    'columns' => [
                        [
                            'name'    => 'Use',
                            'caption' => 'Aktiviert',
                            'width'   => '100px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'ID',
                            'caption' => 'WebFront',
                            'width'   => 'auto',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $WebFrontPushNotification["ID"], "WebFrontPushNotificationConfigurationButton", 1);',
                            'edit'    => [
                                'type'     => 'SelectModule',
                                'moduleID' => self::WEBFRONT_MODULE_GUID
                            ]
                        ]
                    ],
                    'values' => $webFrontPushNotificationValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'name'     => 'WebFrontPushNotificationConfigurationButton',
                    'caption'  => 'Bearbeiten',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ]
            ]
        ];

        ########## E-Mail

        $mailerValues = [];
        foreach (json_decode($this->ReadPropertyString('Mailer')) as $element) {
            $rowColor = '#FFC0C0'; # red
            $id = $element->Mailer;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#C0FFC0'; # light green
                if (!$element->Use) {
                    $rowColor = '#DFDFDF'; # grey
                }
            }
            $mailerValues[] = ['rowColor' => $rowColor];
        }

        ##### Mail panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'E-Mail',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'Mailer',
                    'caption'  => 'Mailer',
                    'rowCount' => 5,
                    'add'      => true,
                    'delete'   => true,
                    'sort'     => [
                        'column'    => 'Mailer',
                        'direction' => 'ascending'
                    ],
                    'columns' => [
                        [
                            'name'    => 'Use',
                            'caption' => 'Aktiviert',
                            'width'   => '100px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'Mailer',
                            'caption' => 'Mailer',
                            'width'   => 'auto',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $Mailer["Mailer"], "MailerConfigurationButton", 1);',
                            'edit'    => [
                                'type'     => 'SelectModule',
                                'moduleID' => self::MAILER_MODULE_GUID
                            ]
                        ]
                    ],
                    'values' => $mailerValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'name'     => 'MailerConfigurationButton',
                    'caption'  => 'Bearbeiten',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ],
            ]
        ];

        ########## SMS

        // NeXXt Mobile (SMS)
        $nexxtMobileValues = [];
        foreach (json_decode($this->ReadPropertyString('NexxtMobile')) as $element) {
            $rowColor = '#FFC0C0'; # red
            $id = $element->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#C0FFC0'; # light green
                if (!$element->Use) {
                    $rowColor = '#DFDFDF'; # grey
                }
            }
            $nexxtMobileValues[] = ['rowColor' => $rowColor];
        }

        // Sipgate (SMS)
        $sipgateValues = [];
        foreach (json_decode($this->ReadPropertyString('Sipgate')) as $element) {
            $rowColor = '#FFC0C0'; # red
            $id = $element->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#C0FFC0'; # light green
                if (!$element->Use) {
                    $rowColor = '#DFDFDF'; # grey
                }
            }
            $sipgateValues[] = ['rowColor' => $rowColor];
        }

        ##### SMS panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'SMS',
            'items'   => [
                // NeXXt Mobile (SMS)
                [
                    'type'     => 'List',
                    'name'     => 'NexxtMobile',
                    'caption'  => 'NeXXt Mobile',
                    'rowCount' => 5,
                    'add'      => true,
                    'delete'   => true,
                    'sort'     => [
                        'column'    => 'ID',
                        'direction' => 'ascending'
                    ],
                    'columns' => [
                        [
                            'name'    => 'Use',
                            'caption' => 'Aktiviert',
                            'width'   => '100px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'ID',
                            'caption' => 'NeXXt Mobile',
                            'width'   => 'auto',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $NexxtMobile["ID"], "NexxtMobileConfigurationButton", 1);',
                            'edit'    => [
                                'type'     => 'SelectModule',
                                'moduleID' => self::NEXXTMOBILE_SMS_MODULE_GUID
                            ]
                        ]
                    ],
                    'values' => $nexxtMobileValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'name'     => 'NexxtMobileConfigurationButton',
                    'caption'  => 'Bearbeiten',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ],
                // Sipgate (SMS)
                [
                    'type'     => 'List',
                    'name'     => 'Sipgate',
                    'caption'  => 'Sipgate',
                    'rowCount' => 5,
                    'add'      => true,
                    'delete'   => true,
                    'sort'     => [
                        'column'    => 'ID',
                        'direction' => 'ascending'
                    ],
                    'columns' => [
                        [
                            'name'    => 'Use',
                            'caption' => 'Aktiviert',
                            'width'   => '100px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'ID',
                            'caption' => 'Sipgate',
                            'width'   => 'auto',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $Sipgate["ID"], "SipgateConfigurationButton", 1);',
                            'edit'    => [
                                'type'     => 'SelectModule',
                                'moduleID' => self::SIPGATE_SMS_MODULE_GUID
                            ]
                        ]
                    ],
                    'values' => $sipgateValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'name'     => 'SipgateConfigurationButton',
                    'caption'  => 'Bearbeiten',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ]
            ]
        ];

        ########## Telegram

        $telegramValues = [];
        foreach (json_decode($this->ReadPropertyString('Telegram')) as $element) {
            $rowColor = '#FFC0C0'; # red
            $id = $element->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#C0FFC0'; # light green
                if (!$element->Use) {
                    $rowColor = '#DFDFDF'; # grey
                }
            }
            $telegramValues[] = ['rowColor' => $rowColor];
        }

        ##### Telegram panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Telegram',
            'items'   => [
                // Telegram (Instant Messaging)
                [
                    'type'     => 'List',
                    'name'     => 'Telegram',
                    'rowCount' => 5,
                    'add'      => true,
                    'delete'   => true,
                    'sort'     => [
                        'column'    => 'ID',
                        'direction' => 'ascending'
                    ],
                    'columns' => [
                        [
                            'name'    => 'Use',
                            'caption' => 'Aktiviert',
                            'width'   => '100px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'ID',
                            'caption' => 'Telegram',
                            'width'   => 'auto',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $Telegram["ID"], "TelegramConfigurationButton", 1);',
                            'edit'    => [
                                'type'     => 'SelectModule',
                                'moduleID' => self::TELEGRAM_BOT_MODULE_GUID
                            ]
                        ]
                    ],
                    'values' => $telegramValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'name'     => 'TelegramConfigurationButton',
                    'caption'  => 'Bearbeiten',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ]
            ]
        ];

        #################### Actions

        ##### Configuration panel

        $form['actions'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Konfiguration',
            'items'   => [
                [
                    'type'    => 'Button',
                    'caption' => 'Neu einlesen',
                    'onClick' => self::MODULE_PREFIX . '_ReloadConfiguration($id);'
                ],
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'SelectCategory',
                            'name'    => 'BackupCategory',
                            'caption' => 'Kategorie',
                            'width'   => '600px'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' '
                        ],
                        [
                            'type'    => 'Button',
                            'caption' => 'Sichern',
                            'onClick' => self::MODULE_PREFIX . '_CreateBackup($id, $BackupCategory);'
                        ]
                    ]
                ],
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'SelectScript',
                            'name'    => 'ConfigurationScript',
                            'caption' => 'Konfiguration',
                            'width'   => '600px'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' '
                        ],
                        [
                            'type'    => 'PopupButton',
                            'caption' => 'Wiederherstellen',
                            'popup'   => [
                                'caption' => 'Konfiguration wirklich wiederherstellen?',
                                'items'   => [
                                    [
                                        'type'    => 'Button',
                                        'caption' => 'Wiederherstellen',
                                        'onClick' => self::MODULE_PREFIX . '_RestoreConfiguration($id, $ConfigurationScript);'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        ##### Template panel

        $form['actions'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Vorlagen',
            'items'   => [
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'SelectInstance',
                            'name'    => 'TemplateInstance',
                            'caption' => 'Instanz',
                            'width'   => '600px'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' '
                        ],
                        [
                            'type'    => 'Button',
                            'caption' => 'Alarmzonensteuerung',
                            'onClick' => self::MODULE_PREFIX . '_AddTemplate($id, $TemplateInstance, 0);'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' '
                        ],
                        [
                            'type'    => 'Button',
                            'caption' => 'Alarmzone',
                            'onClick' => self::MODULE_PREFIX . '_AddTemplate($id, $TemplateInstance, 1);'
                        ]
                    ]
                ]
            ]
        ];

        #################### Status

        $library = IPS_GetLibrary(self::LIBRARY_GUID);
        $version = '[Version ' . $library['Version'] . '-' . $library['Build'] . ' vom ' . date('d.m.Y', $library['Date']) . ']';

        $form['status'] = [
            [
                'code'    => 101,
                'icon'    => 'active',
                'caption' => self::MODULE_NAME . ' wird erstellt',
            ],
            [
                'code'    => 102,
                'icon'    => 'active',
                'caption' => self::MODULE_NAME . ' ist aktiv (ID ' . $this->InstanceID . ') ' . $version,
            ],
            [
                'code'    => 103,
                'icon'    => 'active',
                'caption' => self::MODULE_NAME . ' wird gelöscht (ID ' . $this->InstanceID . ') ' . $version,
            ],
            [
                'code'    => 104,
                'icon'    => 'inactive',
                'caption' => self::MODULE_NAME . ' ist inaktiv (ID ' . $this->InstanceID . ') ' . $version,
            ],
            [
                'code'    => 200,
                'icon'    => 'inactive',
                'caption' => 'Es ist Fehler aufgetreten, weitere Informationen unter Meldungen, im Log oder Debug! (ID ' . $this->InstanceID . ') ' . $version
            ]
        ];
        return json_encode($form);
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    public function AddTemplate(int $TemplateInstance, int $TemplateType): void
    {
        $triggerVariables = json_decode($this->ReadPropertyString('TriggerVariables'), true);
        if ($TemplateInstance == 0 || @!IPS_ObjectExists($TemplateInstance)) {
            return;
        }
        switch ($TemplateType) {
            case 0: # alarm zone control
                $systemStateID = 0;
                $doorWindowStateID = 0;
                $alarmStateID = 0;
                $triggeringDetectorID = 0;
                $children = IPS_GetChildrenIDs($TemplateInstance);
                foreach ($children as $child) {
                    $ident = IPS_GetObject($child)['ObjectIdent'];
                    if ($ident == 'SystemState') {
                        $systemStateID = $child;
                    }
                    if ($ident == 'DoorWindowState') {
                        $doorWindowStateID = $child;
                    }
                    if ($ident == 'AlarmState') {
                        $alarmStateID = $child;
                    }
                    if ($ident == 'AlertingSensor') {
                        $triggeringDetectorID = $child;
                    }
                }
                $config = json_decode(IPS_GetConfiguration($TemplateInstance), true);
                $disarmed = [
                    'Use'                                 => true,
                    'Description'                         => 'Alarmanlage unscharf',
                    'ID'                                  => $systemStateID,
                    'TriggerType'                         => 6,
                    'TriggerValue'                        => '0',
                    'SecondVariable'                      => 0,
                    'SecondVariableValue'                 => '',
                    'Title'                               => $config['Location'],
                    'TriggeringDetector'                  => 0,
                    'MessageText'                         => 'Alarmanlage unscharf!',
                    'UseTimestamp'                        => true,
                    'UseWebFrontNotification'             => false,
                    'WebFrontNotificationTextSymbol'      => '"\ud83d\udfe2"',
                    'Icon'                                => 'Warning',
                    'WebFrontNotificationDisplayDuration' => 0,
                    'UseWebFrontPushNotification'         => true,
                    'WebFrontPushNotificationTextSymbol'  => '"\ud83d\udfe2"',
                    'WebFrontPushNotificationSound'       => '',
                    'WebFrontPushNotificationTargetID'    => 0,
                    'UseMailer'                           => false,
                    'Subject'                             => '',
                    'UseSMS'                              => false,
                    'UseTelegram'                         => false
                ];
                array_push($triggerVariables, $disarmed);

                $armedOpenWindow = [
                    'Use'                                 => true,
                    'Description'                         => 'Alarmanlage bedingt scharf',
                    'ID'                                  => $systemStateID,
                    'TriggerType'                         => 6,
                    'TriggerValue'                        => '1',
                    'SecondVariable'                      => $doorWindowStateID,
                    'SecondVariableValue'                 => 'true',
                    'Title'                               => $config['Location'],
                    'TriggeringDetector'                  => 0,
                    'MessageText'                         => 'Alarmanlage scharf, jedoch stehen noch Türen oder Fenster offen!',
                    'UseTimestamp'                        => true,
                    'UseWebFrontNotification'             => false,
                    'WebFrontNotificationTextSymbol'      => '"\ud83d\udfe1"',
                    'Icon'                                => 'Warning',
                    'WebFrontNotificationDisplayDuration' => 0,
                    'UseWebFrontPushNotification'         => true,
                    'WebFrontPushNotificationTextSymbol'  => '"\ud83d\udfe1"',
                    'WebFrontPushNotificationSound'       => 'alarm',
                    'WebFrontPushNotificationTargetID'    => 0,
                    'UseMailer'                           => false,
                    'Subject'                             => '',
                    'UseSMS'                              => false,
                    'UseTelegram'                         => false
                ];
                array_push($triggerVariables, $armedOpenWindow);

                $armed = [
                    'Use'                                 => true,
                    'Description'                         => 'Alarmanlage scharf',
                    'ID'                                  => $systemStateID,
                    'TriggerType'                         => 6,
                    'TriggerValue'                        => '1',
                    'SecondVariable'                      => $doorWindowStateID,
                    'SecondVariableValue'                 => 'false',
                    'Title'                               => $config['Location'],
                    'TriggeringDetector'                  => 0,
                    'MessageText'                         => 'Alarmanlage scharf!',
                    'UseTimestamp'                        => true,
                    'UseWebFrontNotification'             => false,
                    'WebFrontNotificationTextSymbol'      => '"\ud83d\udd34"',
                    'Icon'                                => 'Warning',
                    'WebFrontNotificationDisplayDuration' => 0,
                    'UseWebFrontPushNotification'         => true,
                    'WebFrontPushNotificationTextSymbol'  => '"\ud83d\udd34"',
                    'WebFrontPushNotificationSound'       => '',
                    'WebFrontPushNotificationTargetID'    => 0,
                    'UseMailer'                           => false,
                    'Subject'                             => '',
                    'UseSMS'                              => false,
                    'UseTelegram'                         => false
                ];
                array_push($triggerVariables, $armed);

                $alarm = [
                    'Use'                                 => true,
                    'Description'                         => 'Alarm',
                    'ID'                                  => $triggeringDetectorID,
                    'TriggerType'                         => 1,
                    'TriggerValue'                        => '',
                    'SecondVariable'                      => $alarmStateID,
                    'SecondVariableValue'                 => '1',
                    'Title'                               => $config['Location'],
                    'TriggeringDetector'                  => $triggeringDetectorID,
                    'MessageText'                         => '%1$s hat einen Alarm ausgelöst!',
                    'UseTimestamp'                        => true,
                    'UseWebFrontNotification'             => false,
                    'WebFrontNotificationTextSymbol'      => '"\u2757"',
                    'Icon'                                => 'Warning',
                    'WebFrontNotificationDisplayDuration' => 0,
                    'UseWebFrontPushNotification'         => true,
                    'WebFrontPushNotificationTextSymbol'  => '"\u2757"',
                    'WebFrontPushNotificationSound'       => 'alarm',
                    'WebFrontPushNotificationTargetID'    => 0,
                    'UseMailer'                           => true,
                    'Subject'                             => 'Alarmauslösung ' . $config['Location'],
                    'UseSMS'                              => false,
                    'UseTelegram'                         => false
                ];
                array_push($triggerVariables, $alarm);
                break;

            case 1: # alarm zone
                $alarmZoneID = 0;
                $doorWindowStateID = 0;
                $alarmStateID = 0;
                $triggeringDetectorID = 0;
                $children = IPS_GetChildrenIDs($TemplateInstance);
                foreach ($children as $child) {
                    $ident = IPS_GetObject($child)['ObjectIdent'];
                    if ($ident == 'AlarmZoneState') {
                        $alarmZoneID = $child;
                    }
                    if ($ident == 'DoorWindowState') {
                        $doorWindowStateID = $child;
                    }
                    if ($ident == 'AlarmState') {
                        $alarmStateID = $child;
                    }
                    if ($ident == 'AlertingSensor') {
                        $triggeringDetectorID = $child;
                    }
                }
                $config = json_decode(IPS_GetConfiguration($TemplateInstance), true);
                $disarmed = [
                    'Use'                                 => true,
                    'Description'                         => $config['SystemName'] . ' unscharf',
                    'ID'                                  => $alarmZoneID,
                    'TriggerType'                         => 6,
                    'TriggerValue'                        => '0',
                    'SecondVariable'                      => 0,
                    'SecondVariableValue'                 => '',
                    'Title'                               => $config['Location'],
                    'TriggeringDetector'                  => 0,
                    'MessageText'                         => $config['SystemName'] . ' unscharf!',
                    'UseTimestamp'                        => true,
                    'UseWebFrontNotification'             => false,
                    'WebFrontNotificationTextSymbol'      => '"\ud83d\udfe2"',
                    'Icon'                                => 'Warning',
                    'WebFrontNotificationDisplayDuration' => 0,
                    'UseWebFrontPushNotification'         => true,
                    'WebFrontPushNotificationTextSymbol'  => '"\ud83d\udfe2"',
                    'WebFrontPushNotificationSound'       => '',
                    'WebFrontPushNotificationTargetID'    => 0,
                    'UseMailer'                           => false,
                    'Subject'                             => '',
                    'UseSMS'                              => false,
                    'UseTelegram'                         => false
                ];
                array_push($triggerVariables, $disarmed);

                $armedOpenWindow = [
                    'Use'                                 => true,
                    'Description'                         => $config['SystemName'] . ' bedingt scharf',
                    'ID'                                  => $alarmZoneID,
                    'TriggerType'                         => 6,
                    'TriggerValue'                        => '1',
                    'SecondVariable'                      => $doorWindowStateID,
                    'SecondVariableValue'                 => 'true',
                    'Title'                               => $config['Location'],
                    'TriggeringDetector'                  => 0,
                    'MessageText'                         => $config['SystemName'] . ' scharf, jedoch stehen noch Türen oder Fenster offen!',
                    'UseTimestamp'                        => true,
                    'UseWebFrontNotification'             => false,
                    'WebFrontNotificationTextSymbol'      => '"\ud83d\udfe1"',
                    'Icon'                                => 'Warning',
                    'WebFrontNotificationDisplayDuration' => 0,
                    'UseWebFrontPushNotification'         => true,
                    'WebFrontPushNotificationTextSymbol'  => '"\ud83d\udfe1"',
                    'WebFrontPushNotificationSound'       => 'alarm',
                    'WebFrontPushNotificationTargetID'    => 0,
                    'UseMailer'                           => false,
                    'Subject'                             => '',
                    'UseSMS'                              => false,
                    'UseTelegram'                         => false
                ];
                array_push($triggerVariables, $armedOpenWindow);

                $armed = [
                    'Use'                                 => true,
                    'Description'                         => $config['SystemName'] . ' scharf',
                    'ID'                                  => $alarmZoneID,
                    'TriggerType'                         => 6,
                    'TriggerValue'                        => '1',
                    'SecondVariable'                      => $doorWindowStateID,
                    'SecondVariableValue'                 => 'false',
                    'Title'                               => $config['Location'],
                    'TriggeringDetector'                  => 0,
                    'MessageText'                         => $config['SystemName'] . ' scharf!',
                    'UseTimestamp'                        => true,
                    'UseWebFrontNotification'             => false,
                    'WebFrontNotificationTextSymbol'      => '"\ud83d\udd34"',
                    'Icon'                                => 'Warning',
                    'WebFrontNotificationDisplayDuration' => 0,
                    'UseWebFrontPushNotification'         => true,
                    'WebFrontPushNotificationTextSymbol'  => '"\ud83d\udd34"',
                    'WebFrontPushNotificationSound'       => '',
                    'WebFrontPushNotificationTargetID'    => 0,
                    'UseMailer'                           => false,
                    'Subject'                             => '',
                    'UseSMS'                              => false,
                    'UseTelegram'                         => false
                ];
                array_push($triggerVariables, $armed);

                $alarm = [
                    'Use'                                 => true,
                    'Description'                         => 'Alarm',
                    'ID'                                  => $triggeringDetectorID,
                    'TriggerType'                         => 1,
                    'TriggerValue'                        => '',
                    'SecondVariable'                      => $alarmStateID,
                    'SecondVariableValue'                 => '1',
                    'Title'                               => $config['Location'],
                    'TriggeringDetector'                  => $triggeringDetectorID,
                    'MessageText'                         => '%1$s hat einen Alarm ausgelöst!',
                    'UseTimestamp'                        => true,
                    'UseWebFrontNotification'             => false,
                    'WebFrontNotificationTextSymbol'      => '"\u2757"',
                    'Icon'                                => 'Warning',
                    'WebFrontNotificationDisplayDuration' => 0,
                    'UseWebFrontPushNotification'         => true,
                    'WebFrontPushNotificationTextSymbol'  => '"\u2757"',
                    'WebFrontPushNotificationSound'       => 'alarm',
                    'WebFrontPushNotificationTargetID'    => 0,
                    'UseMailer'                           => true,
                    'Subject'                             => 'Alarmauslösung ' . $config['Location'],
                    'UseSMS'                              => false,
                    'UseTelegram'                         => false
                ];
                array_push($triggerVariables, $alarm);
                break;
        }

        IPS_SetProperty($this->InstanceID, 'TriggerVariables', json_encode($triggerVariables));
        if (IPS_HasChanges($this->InstanceID)) {
            IPS_ApplyChanges($this->InstanceID);
        }
        echo 'Die Vorlage wurde erfolgreich angelegt!';
    }

    public function ShowVariableDetails(int $VariableID): void
    {
        if ($VariableID == 0 || !@IPS_ObjectExists($VariableID)) {
            return;
        }
        if ($VariableID != 0) {
            // Variable
            echo 'ID: ' . $VariableID . "\n";
            echo 'Name: ' . IPS_GetName($VariableID) . "\n";
            $variable = IPS_GetVariable($VariableID);
            if (!empty($variable)) {
                $variableType = $variable['VariableType'];
                switch ($variableType) {
                    case 0:
                        $variableTypeName = 'Boolean';
                        break;

                    case 1:
                        $variableTypeName = 'Integer';
                        break;

                    case 2:
                        $variableTypeName = 'Float';
                        break;

                    case 3:
                        $variableTypeName = 'String';
                        break;

                    default:
                        $variableTypeName = 'Unbekannt';
                }
                echo 'Variablentyp: ' . $variableTypeName . "\n";
            }
            // Profile
            $profile = @IPS_GetVariableProfile($variable['VariableProfile']);
            if (empty($profile)) {
                $profile = @IPS_GetVariableProfile($variable['VariableCustomProfile']);
            }
            if (!empty($profile)) {
                $profileType = $variable['VariableType'];
                switch ($profileType) {
                    case 0:
                        $profileTypeName = 'Boolean';
                        break;

                    case 1:
                        $profileTypeName = 'Integer';
                        break;

                    case 2:
                        $profileTypeName = 'Float';
                        break;

                    case 3:
                        $profileTypeName = 'String';
                        break;

                    default:
                        $profileTypeName = 'Unbekannt';
                }
                echo 'Profilname: ' . $profile['ProfileName'] . "\n";
                echo 'Profiltyp: ' . $profileTypeName . "\n\n";
            }
            if (!empty($variable)) {
                echo "\nVariable:\n";
                print_r($variable);
            }
            if (!empty($profile)) {
                echo "\nVariablenprofil:\n";
                print_r($profile);
            }
        }
    }

    public function EnableConfigurationButton(int $ObjectID, string $ButtonName, int $Type): void
    {
        // Variable
        $description = 'ID ' . $ObjectID . ' bearbeiten';
        // Instance
        if ($Type == 1) {
            $description = 'ID ' . $ObjectID . ' konfigurieren';
        }
        $this->UpdateFormField($ButtonName, 'caption', $description);
        $this->UpdateFormField($ButtonName, 'visible', true);
        $this->UpdateFormField($ButtonName, 'enabled', true);
        $this->UpdateFormField($ButtonName, 'objectID', $ObjectID);
    }

    #################### Request action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Notification':
                $this->SetValue($Ident, $Value);
                break;
        }
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    private function CheckMaintenanceMode(): bool
    {
        $result = $this->ReadPropertyBoolean('MaintenanceMode');
        if ($result) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, der Wartungsmodus ist aktiv!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', Abbruch, der Wartungsmodus ist aktiv!', KL_WARNING);
        }
        return $result;
    }
}
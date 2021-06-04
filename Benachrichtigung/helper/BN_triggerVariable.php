<?php

/** @noinspection PhpUnused */

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Benachrichtigung/tree/main/Benachrichtigung
 */

declare(strict_types=1);

trait BN_triggerVariable
{
    public function CheckTriggerVariable(int $VariableID, bool $ValueChanged): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $triggerVariables = json_decode($this->ReadPropertyString('TriggerVariables'), true);
        if (!empty($triggerVariables)) {
            // Check if variable is listed
            $keys = array_keys(array_column($triggerVariables, 'ID'), $VariableID);
            foreach ($keys as $key) {
                if (!$triggerVariables[$key]['Use']) {
                    continue;
                }
                $triggered = false;
                $type = IPS_GetVariable($VariableID)['VariableType'];
                $triggerValue = $triggerVariables[$key]['TriggerValue'];
                switch ($triggerVariables[$key]['TriggerType']) {
                    case 0: # on change (bool, integer, float, string)
                        if ($ValueChanged) {
                            $this->SendDebug(__FUNCTION__, 'Auslösebedingung: Bei Änderung (bool, integer, float, string)', 0);
                            $triggered = true;
                        }
                        break;

                    case 1: # on update (bool, integer, float, string)
                        $this->SendDebug(__FUNCTION__, 'Auslösebedingung: Bei Aktualisierung (bool, integer, float, string)', 0);
                        $triggered = true;
                        break;

                    case 2: # on limit drop, once (integer, float)
                        switch ($type) {
                            case 1: # integer
                                if ($ValueChanged) {
                                    if ($triggerValue == 'false') {
                                        $triggerValue = '0';
                                    }
                                    if ($triggerValue == 'true') {
                                        $triggerValue = '1';
                                    }
                                    if (GetValueInteger($VariableID) < intval($triggerValue)) {
                                        $this->SendDebug(__FUNCTION__, 'Auslösebedingung: Bei Grenzunterschreitung, einmalig (integer)', 0);
                                        $triggered = true;
                                    }
                                }
                                break;

                            case 2: # float
                                if ($ValueChanged) {
                                    if ($triggerValue == 'false') {
                                        $triggerValue = '0';
                                    }
                                    if ($triggerValue == 'true') {
                                        $triggerValue = '1';
                                    }
                                    if (GetValueFloat($VariableID) < floatval(str_replace(',', '.', $triggerValue))) {
                                        $this->SendDebug(__FUNCTION__, 'Auslösebedingung: Bei Grenzunterschreitung, einmalig (float)', 0);
                                        $triggered = true;
                                    }
                                }
                                break;

                        }
                        break;

                    case 3: # on limit drop, every time (integer, float)
                        switch ($type) {
                            case 1: # integer
                                if ($triggerValue == 'false') {
                                    $triggerValue = '0';
                                }
                                if ($triggerValue == 'true') {
                                    $triggerValue = '1';
                                }
                                if (GetValueInteger($VariableID) < intval($triggerValue)) {
                                    $this->SendDebug(__FUNCTION__, 'Auslösebedingung: Bei Grenzunterschreitung, mehrmalig (integer)', 0);
                                    $triggered = true;
                                }
                                break;

                            case 2: # float
                                if ($triggerValue == 'false') {
                                    $triggerValue = '0';
                                }
                                if ($triggerValue == 'true') {
                                    $triggerValue = '1';
                                }
                                if (GetValueFloat($VariableID) < floatval(str_replace(',', '.', $triggerValue))) {
                                    $this->SendDebug(__FUNCTION__, 'Auslösebedingung: Bei Grenzunterschreitung, mehrmalig (float)', 0);
                                    $triggered = true;
                                }
                                break;

                        }
                        break;

                    case 4: # on limit exceed, once (integer, float)
                        switch ($type) {
                            case 1: # integer
                                if ($ValueChanged) {
                                    if ($triggerValue == 'false') {
                                        $triggerValue = '0';
                                    }
                                    if ($triggerValue == 'true') {
                                        $triggerValue = '1';
                                    }
                                    if (GetValueInteger($VariableID) > intval($triggerValue)) {
                                        $this->SendDebug(__FUNCTION__, 'Auslösebedingung: Bei Grenzunterschreitung, einmalig (integer)', 0);
                                        $triggered = true;
                                    }
                                }
                                break;

                            case 2: # float
                                if ($ValueChanged) {
                                    if ($triggerValue == 'false') {
                                        $triggerValue = '0';
                                    }
                                    if ($triggerValue == 'true') {
                                        $triggerValue = '1';
                                    }
                                    if (GetValueFloat($VariableID) > floatval(str_replace(',', '.', $triggerValue))) {
                                        $this->SendDebug(__FUNCTION__, 'Auslösebedingung: Bei Grenzunterschreitung, einmalig (float)', 0);
                                        $triggered = true;
                                    }
                                }
                                break;

                        }
                        break;

                    case 5: # on limit exceed, every time (integer, float)
                        switch ($type) {
                            case 1: # integer
                                if ($triggerValue == 'false') {
                                    $triggerValue = '0';
                                }
                                if ($triggerValue == 'true') {
                                    $triggerValue = '1';
                                }
                                if (GetValueInteger($VariableID) > intval($triggerValue)) {
                                    $this->SendDebug(__FUNCTION__, 'Auslösebedingung: Bei Grenzunterschreitung, mehrmalig (integer)', 0);
                                    $triggered = true;
                                }
                                break;

                            case 2: # float
                                if ($triggerValue == 'false') {
                                    $triggerValue = '0';
                                }
                                if ($triggerValue == 'true') {
                                    $triggerValue = '1';
                                }
                                if (GetValueFloat($VariableID) > floatval(str_replace(',', '.', $triggerValue))) {
                                    $this->SendDebug(__FUNCTION__, 'Auslösebedingung: Bei Grenzunterschreitung, mehrmalig (float)', 0);
                                    $triggered = true;
                                }
                                break;

                        }
                        break;

                    case 6: # on specific value, once (bool, integer, float, string)
                        switch ($type) {
                            case 0: #bool
                                if ($ValueChanged) {
                                    if ($triggerValue == 'false') {
                                        $triggerValue = '0';
                                    }
                                    if (GetValueBoolean($VariableID) == boolval($triggerValue)) {
                                        $this->SendDebug(__FUNCTION__, 'Auslösebedingung: Bei bestimmten Wert, einmalig (bool)', 0);
                                        $triggered = true;
                                    }
                                }
                                break;

                            case 1: # integer
                                if ($ValueChanged) {
                                    if ($triggerValue == 'false') {
                                        $triggerValue = '0';
                                    }
                                    if ($triggerValue == 'true') {
                                        $triggerValue = '1';
                                    }
                                    if (GetValueInteger($VariableID) == intval($triggerValue)) {
                                        $this->SendDebug(__FUNCTION__, 'Auslösebedingung: Bei bestimmten Wert, einmalig (integer)', 0);
                                        $triggered = true;
                                    }
                                }
                                break;

                            case 2: # float
                                if ($ValueChanged) {
                                    if ($triggerValue == 'false') {
                                        $triggerValue = '0';
                                    }
                                    if ($triggerValue == 'true') {
                                        $triggerValue = '1';
                                    }
                                    if (GetValueFloat($VariableID) == floatval(str_replace(',', '.', $triggerValue))) {
                                        $this->SendDebug(__FUNCTION__, 'Auslösebedingung: Bei bestimmten Wert, einmalig (float)', 0);
                                        $triggered = true;
                                    }
                                }
                                break;

                            case 3: # string
                                if ($ValueChanged) {
                                    if (GetValueString($VariableID) == (string) $triggerValue) {
                                        $this->SendDebug(__FUNCTION__, 'Auslösebedingung: Bei bestimmten Wert, einmalig (string)', 0);
                                        $triggered = true;
                                    }
                                }
                                break;

                        }
                        break;

                    case 7: # on specific value, every time (bool, integer, float, string)
                        switch ($type) {
                            case 0: # bool
                                if ($triggerValue == 'false') {
                                    $triggerValue = '0';
                                }
                                if (GetValueBoolean($VariableID) == boolval($triggerValue)) {
                                    $this->SendDebug(__FUNCTION__, 'Auslösebedingung: Bei bestimmten Wert, mehrmalig (bool)', 0);
                                    $triggered = true;
                                }
                                break;

                            case 1: # integer
                                if ($triggerValue == 'false') {
                                    $triggerValue = '0';
                                }
                                if ($triggerValue == 'true') {
                                    $triggerValue = '1';
                                }
                                if (GetValueInteger($VariableID) == intval($triggerValue)) {
                                    $this->SendDebug(__FUNCTION__, 'Auslösebedingung: Bei bestimmten Wert, mehrmalig (integer)', 0);
                                    $triggered = true;
                                }
                                break;

                            case 2: # float
                                if ($triggerValue == 'false') {
                                    $triggerValue = '0';
                                }
                                if ($triggerValue == 'true') {
                                    $triggerValue = '1';
                                }
                                if (GetValueFloat($VariableID) == floatval(str_replace(',', '.', $triggerValue))) {
                                    $this->SendDebug(__FUNCTION__, 'Auslösebedingung: Bei bestimmten Wert, mehrmalig (float)', 0);
                                    $triggered = true;
                                }
                                break;

                            case 3: # string
                                if (GetValueString($VariableID) == (string) $triggerValue) {
                                    $this->SendDebug(__FUNCTION__, 'Auslösebedingung: Bei bestimmten Wert, mehrmalig (string)', 0);
                                    $triggered = true;
                                }
                                break;

                        }
                        break;
                }
                if ($triggered) {
                    $execute = false;
                    $secondVariable = $triggerVariables[$key]['SecondVariable'];
                    if ($secondVariable != 0 && @IPS_ObjectExists($secondVariable)) {
                        $type = IPS_GetVariable($secondVariable)['VariableType'];
                        $value = $triggerVariables[$key]['SecondVariableValue'];
                        switch ($type) {
                            case 0: # bool
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if (GetValueBoolean($secondVariable) == boolval($value)) {
                                    $this->SendDebug(__FUNCTION__, 'Auslösebedingung der weiteren Variable: Bei bestimmten Wert, mehrmalig (bool)', 0);
                                    $execute = true;
                                }
                                break;

                            case 1: # integer
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueInteger($secondVariable) == intval($value)) {
                                    $this->SendDebug(__FUNCTION__, 'Auslösebedingung der weiteren Variable: Bei bestimmten Wert, mehrmalig (integer)', 0);
                                    $execute = true;
                                }
                                break;

                            case 2: # float
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueFloat($secondVariable) == floatval(str_replace(',', '.', $value))) {
                                    $this->SendDebug(__FUNCTION__, 'Auslösebedingung der weiteren Variable: Bei bestimmten Wert, mehrmalig (float)', 0);
                                    $execute = true;
                                }
                                break;

                            case 3: # string
                                if (GetValueString($secondVariable) == (string) $value) {
                                    $this->SendDebug(__FUNCTION__, 'Auslösebedingung der weiteren Variable: Bei bestimmten Wert, mehrmalig (string)', 0);
                                    $execute = true;
                                }
                                break;

                        }
                    }
                    if ($secondVariable == 0) {
                        $execute = true;
                    }
                    if ($execute) {
                        // Prepare data
                        $title = $triggerVariables[$key]['Title'];
                        $triggeringDetector = $triggerVariables[$key]['TriggeringDetector'];
                        $messageText = $triggerVariables[$key]['MessageText'];
                        $useTimestamp = $triggerVariables[$key]['UseTimestamp'];
                        $timeStamp = date('d.m.Y, H:i:s');
                        if ($useTimestamp) {
                            $messageText = $messageText . ' ' . $timeStamp;
                        }
                        $useWebFrontNotification = $triggerVariables[$key]['UseWebFrontNotification'];
                        $webFrontNotificationTextSymbol = $triggerVariables[$key]['WebFrontNotificationTextSymbol'];
                        $webFrontNotificationIcon = $triggerVariables[$key]['WebFrontNotificationIcon'];
                        $webFrontNotificationDisplayDuration = $triggerVariables[$key]['WebFrontNotificationDisplayDuration'];
                        $useWebFrontPushNotification = $triggerVariables[$key]['UseWebFrontPushNotification'];
                        $webFrontPushNotificationTextSymbol = $triggerVariables[$key]['WebFrontPushNotificationTextSymbol'];
                        $webFrontPushNotificationSound = $triggerVariables[$key]['WebFrontPushNotificationSound'];
                        $webFrontPushNotificationTargetID = $triggerVariables[$key]['WebFrontPushNotificationTargetID'];
                        $useMailer = $triggerVariables[$key]['UseMailer'];
                        $subject = $triggerVariables[$key]['Subject'];
                        $useSMS = $triggerVariables[$key]['UseSMS'];
                        $useTelegram = $triggerVariables[$key]['UseTelegram'];
                        // Create message text
                        if ($triggeringDetector != 0 && @IPS_ObjectExists($triggeringDetector)) {
                            $triggeringDetectorType = IPS_GetVariable($triggeringDetector)['VariableType'];
                            if ($triggeringDetectorType == 3) {
                                $messageText = sprintf($messageText, GetValueString($triggeringDetector));
                                $this->SendDebug(__FUNCTION__, $messageText, 0);
                            }
                        }
                        // WebFront notification
                        if ($useWebFrontNotification) {
                            $webFrontNotificationText = $messageText;
                            if (!empty($webFrontNotificationTextSymbol)) {
                                $webFrontNotificationText = "\n" . json_decode($webFrontNotificationTextSymbol) . ' ' . $messageText;
                            }
                            $this->SendWebFrontNotification($title, $webFrontNotificationText, $webFrontNotificationIcon, $webFrontNotificationDisplayDuration);
                        }
                        // WebFront push notification
                        if ($useWebFrontPushNotification) {
                            $webFrontPushNotificationText = $messageText;
                            if (!empty($webFrontPushNotificationTextSymbol)) {
                                $webFrontPushNotificationText = "\n" . json_decode($webFrontPushNotificationTextSymbol) . ' ' . $messageText;
                            }
                            $this->SendWebFrontPushNotification($title, $webFrontPushNotificationText, $webFrontPushNotificationSound, $webFrontPushNotificationTargetID);
                        }
                        // E-Mail
                        if ($useMailer) {
                            $this->SendMailNotification($subject, $title . "\n\n" . $messageText);
                        }

                        // SMS
                        if ($useSMS) {
                            $this->SendNexxtMobileSMS($title . "\n\n" . $messageText);
                            $this->SendSipgateSMS($title . "\n\n" . $messageText);
                        }

                        // Telegram
                        if ($useTelegram) {
                            $this->SendTelegramMessage($title . "\n\n" . $messageText);
                        }
                    }
                }
            }
        }
    }
}
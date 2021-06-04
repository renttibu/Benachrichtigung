<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Benachrichtigung/tree/main/Benachrichtigung
 */

declare(strict_types=1);

trait BN_notification
{
    public function SendWebFrontNotification(string $Title, string $Text, string $Icon, int $DisplayDuration): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        foreach (json_decode($this->ReadPropertyString('WebFrontNotification')) as $element) {
            if (!$element->Use) {
                continue;
            }
            $id = $element->ID;
            if ($id == 0 || @!IPS_ObjectExists($id)) {
                continue;
            }
            //@WFC_SendNotification($id, $Title, $Text, $Icon, $DisplayDuration);
            $scriptText = 'WFC_SendNotification(' . $id . ', "' . $Title . '", "' . $Text . '", "' . $Icon . '", ' . $DisplayDuration . ');';
            IPS_RunScriptText($scriptText);
        }
    }

    public function SendWebFrontPushNotification(string $Title, string $Text, string $Sound, int $TargetID = 0): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        // Title length max 32 characters
        $Title = substr($Title, 0, 32);
        // Text length max 256 characters
        $Text = substr($Text, 0, 256);
        foreach (json_decode($this->ReadPropertyString('WebFrontPushNotification')) as $element) {
            if (!$element->Use) {
                continue;
            }
            $id = $element->ID;
            if ($id == 0 || @!IPS_ObjectExists($id)) {
                continue;
            }
            //@WFC_PushNotification($id, $Title, $Text, $Sound, $TargetID);
            $scriptText = 'WFC_PushNotification(' . $id . ', "' . $Title . '", "' . $Text . '", "' . $Sound . '", ' . $TargetID . ');';
            IPS_RunScriptText($scriptText);
        }
    }

    public function SendMailNotification(string $Subject, string $Text): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        foreach (json_decode($this->ReadPropertyString('Mailer')) as $element) {
            if (!$element->Use) {
                continue;
            }
            $id = $element->Mailer;
            if ($id == 0 || @!IPS_ObjectExists($id)) {
                continue;
            }
            //@MA_SendMessage($id, $Subject, $Text);
            $scriptText = 'MA_SendMessage(' . $id . ', "' . $Subject . '", "' . $Text . '");';
            IPS_RunScriptText($scriptText);
        }
    }

    public function SendNexxtMobileSMS(string $Text): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        // Text length max 160 characters
        $Text = substr($Text, 0, 160);
        foreach (json_decode($this->ReadPropertyString('NexxtMobile')) as $element) {
            if (!$element->Use) {
                continue;
            }
            $id = $element->ID;
            if ($id == 0 || @!IPS_ObjectExists($id)) {
                continue;
            }
            //@SMSNM_SendMessage($id, $Text);
            $scriptText = 'SMSNM_SendMessage(' . $id . ', "' . $Text . '");';
            IPS_RunScriptText($scriptText);
        }
    }

    public function SendSipgateSMS(string $Text): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        // Text length max 160 characters
        $Text = substr($Text, 0, 160);
        foreach (json_decode($this->ReadPropertyString('Sipgate')) as $element) {
            if (!$element->Use) {
                continue;
            }
            $id = $element->ID;
            if ($id == 0 || @!IPS_ObjectExists($id)) {
                continue;
            }
            //@SMSSG_SendMessage($id, $Text);
            $scriptText = 'SMSSG_SendMessage(' . $id . ', "' . $Text . '");';
            IPS_RunScriptText($scriptText);
        }
    }

    public function SendTelegramMessage(string $Text): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        foreach (json_decode($this->ReadPropertyString('Telegram')) as $element) {
            if (!$element->Use) {
                continue;
            }
            $id = $element->ID;
            if ($id == 0 || @!IPS_ObjectExists($id)) {
                continue;
            }
            //@TB_SendMessage($id, $Text);
            $scriptText = 'TB_SendMessage(' . $id . ', "' . $Text . '");';
            IPS_RunScriptText($scriptText);
        }
    }
}
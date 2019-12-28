<?php
include_once 'IInteractor.php';
include_once 'NevoboGateway.php';
include_once 'TelFluitGateway.php';
include_once 'EmailGateway.php';
include_once 'ZaalwachtGateway.php';
include_once 'BarcieGateway.php';
include_once 'JoomlaGateway.php';
include_once 'Email.php';

class Template
{
    public const NAAM = "{{naam}}";
    public const DATUM = "{{datum}}";
    public const TIJD = "{{tijd}}";
    public const USER_ID = "{{userId}}";
    public const TEAM = "{{team}}";
    public const TEAMS = "{{teams}}";
    public const AFZENDER = "{{afzender}}";
    public const SHIFT = "{{afzender}}";
    public const BHV = "{{bhv}}";
    public const SCHEIDSRECHTERS = "{{scheidsrechters}}";
    public const TELLERS = "{{tellers}}";
    public const ZAALWACHTERS = "{{zaalwachters}}";
    public const BARCIELEDEN = "{{barcieleden}}";
}

class QueueWeeklyEmails implements IInteractor
{
    private $scheidsco;
    private $webcie;

    public function __construct($database)
    {
        $this->nevoboGateway = new NevoboGateway();
        $this->telFluitGateway = new TelFluitGateway($database);
        $this->zaalwachtGateway = new ZaalwachtGateway($database);
        $this->mailQueueGateway = new EmailGateway($database);
        $this->barcieGateway = new BarcieGateway($database);
        $this->joomlaGateway = new JoomlaGateway($database);
    }

    public function Execute()
    {
        $isServerRequest = $_SERVER['SERVER_ADDR'] === $_SERVER['REMOTE_ADDR'];
        if (!$isServerRequest) {
            throw new UnexpectedValueException("Dit is niet een publieke api...");
        }

        $this->scheidsco = $this->joomlaGateway->GetUser(2223); // E. vd B.
        $this->webcie = $this->joomlaGateway->GetUser(542);

        $wedstrijddagen = $this->nevoboGateway->GetWedstrijddagenForSporthal('LDNUN');
        foreach ($wedstrijddagen as $dag) {
            $dag->barcieleden = $this->barcieGateway->GetBarciedienstenForDate($dag->date);
            $dag->zaalwacht = $this->zaalwachtGateway->GetZaalwachtTeamForDate($dag->date);
            if ($dag->zaalwacht) {
                $dag->zaalwacht->teamgenoten = $this->joomlaGateway->GetTeamgenoten($dag->zaalwacht->naam);
            }

            foreach ($dag->wedstrijden as $wedstrijd) {
                list($scheidsrechter, $telteam) = $this->telFluitGateway->GetScheidsrechterAndTellersForWedstrijd($wedstrijd->id);
                $wedstrijd->scheidsrechter = $scheidsrechter;
                $wedstrijd->telteam = $telteam;
                if ($wedstrijd->telteam) {
                    $wedstrijd->telteam->spelers = $this->joomlaGateway->GetTeamgenoten($telteam->naam);
                }
            }
        }

        $emails = $this->GetAllEmails($wedstrijddagen);

        $this->mailQueueGateway->QueueEmails($emails);
    }

    private function GetAllEmails($wedstrijdagen)
    {
        $emails = [];

        foreach ($wedstrijdagen as $dag) {
            foreach ($dag->wedstrijden as $wedstrijd) {
                if ($wedstrijd->scheidsrechter) {
                    $emails[] = $this->CreateScheidsrechterMail($wedstrijd);
                }

                if ($wedstrijd->telteam) {
                    foreach ($wedstrijd->telteam->spelers as $teller) {
                        $emails[] = $this->CreateTellerMail($teller, $wedstrijd);
                    }
                }
            }

            if ($dag->zaalwacht) {
                foreach ($dag->zaalwacht->teamgenoten as $zaalwachter) {
                    $emails[] = $this->CreateZaalwachtMail($zaalwachter, $dag);
                }
            }

            foreach ($dag->barcieleden as $barcielid) {
                $emails[] = $this->CreateBarcieMail($barcielid, $dag);
            }
        }

        $emails[] = $this->CreateSamenvattingMail($wedstrijdagen, $this->webcie);
        $emails[] = $this->CreateSamenvattingMail($wedstrijdagen, $this->scheidsco);

        return $emails;
    }

    private function CreateScheidsrechterMail($wedstrijd)
    {
        $scheidsrechter = $wedstrijd->scheidsrechter;

        $datum = GetDutchDate($wedstrijd->timestamp);
        $tijd = $wedstrijd->timestamp->format('G:i');
        $naam = $scheidsrechter->naam;
        $userId = $scheidsrechter->id;
        $team = $scheidsrechter->team ?? "je team";
        $spelendeTeams = $wedstrijd->team1 . " - " . $wedstrijd->team2;

        $template = file_get_contents("./UseCases/Email/templates/scheidsrechterTemplate.txt");
        $placeholders = [
            Template::DATUM => $datum,
            Template::TIJD => $tijd,
            Template::NAAM => $naam,
            Template::USER_ID => $userId,
            Template::TEAM => $team,
            Template::TEAMS => $spelendeTeams,
            Template::AFZENDER => $this->scheidsco->naam
        ];
        $body = Email::FillTemplate($template, $placeholders);

        $titel = "Fluiten " . $spelendeTeams;
        return new Email(
            $titel,
            $body,
            $scheidsrechter,
            $this->scheidsco
        );
    }

    private function CreateTellerMail($teller, $wedstrijd)
    {
        $datum = GetDutchDate($wedstrijd->timestamp);
        $tijd = $wedstrijd->timestamp->format('G:i');
        $naam = $teller->naam;
        $userId = $teller->id;
        $spelendeTeams = $wedstrijd->team1 . " - " . $wedstrijd->team2;

        $body = file_get_contents("./UseCases/Email/templates/tellerTemplate.txt");
        $body = str_replace(Template::DATUM, $datum, $body);
        $body = str_replace(Template::TIJD, $tijd, $body);
        $body = str_replace(Template::NAAM, $naam, $body);
        $body = str_replace(Template::USER_ID, $userId, $body);
        $body = str_replace(Template::TEAMS, $spelendeTeams, $body);
        $body = str_replace(Template::AFZENDER, $this->scheidsco->naam, $body);

        $titel = "Tellen " . $spelendeTeams;
        return new Email(
            $titel,
            $body,
            $teller,
            $this->scheidsco
        );
    }

    private function CreateZaalwachtMail($zaalwachter, $wedstrijddag)
    {
        $naam = $zaalwachter->naam;
        $datum = GetDutchDateLong($wedstrijddag->date);

        $template = file_get_contents("./UseCases/Email/templates/zaalwachtTemplate.txt");
        $placeholders = [
            Template::NAAM => $naam,
            Template::DATUM => $datum,
            Template::AFZENDER => $this->scheidsco->naam,
        ];
        $body = Email::FillTemplate($template, $placeholders);

        $titel = "Zaalwacht " . $datum;
        return new Email(
            $titel,
            $body,
            $zaalwachter,
            $this->scheidsco
        );
    }

    private function CreateBarcieMail($barcielid, $dag)
    {
        $datum = GetDutchDateLong($dag->date);
        $naam = $barcielid->naam;
        $shift = $barcielid->shift;
        $bhv = $barcielid->isBhv == 1 ? "<br>Je bent BHV'er." : "";

        $template = file_get_contents("./UseCases/Email/templates/barcieTemplate.txt");
        $placeholders = [
            Template::DATUM => $datum,
            Template::NAAM => $naam,
            Template::SHIFT => $shift,
            Template::BHV => $bhv,
            Template::AFZENDER => $this->scheidsco->naam
        ];
        $body = Email::FillTemplate($template, $placeholders);

        return new Email(
            "Barciedienst " . $datum,
            $body,
            $barcielid,
            $this->scheidsco
        );
    }

    private function CreateSamenvattingMail($wedstrijddagen, $receiver)
    {
        $barcieContent = "";
        $scheidsrechtersContent = "";
        $tellersContent = "";
        $zaalwachtersContent = "";

        foreach ($wedstrijddagen as $dag) {
            foreach ($dag->barcieleden as $barcielid) {
                $barcieContent  .= $this->GetNaamAndEmail($barcielid->naam, $barcielid->email);
            }
            if ($dag->zaalwacht) {
                $zaalwachtersContent .= $this->GetBoldHeader($dag->zaalwacht->naam);
                foreach ($dag->zaalwacht->teamgenoten as $persoon) {
                    $zaalwachtersContent .= $this->GetNaamAndEmail($persoon);
                }
                $zaalwachtersContent .= $this->GetNewLine();
            }

            foreach ($dag->wedstrijden as $wedstrijd) {
                if ($wedstrijd->scheidsrechter) {
                    $scheidsrechtersContent .= $this->GetNaamAndEmail($wedstrijd->scheidsrechter);
                }
                if ($wedstrijd->telteam) {
                    $tellersContent .= $this->GetBoldHeader($wedstrijd->telteam->naam);
                    foreach ($wedstrijd->telteam->teamgenoten as $teller) {
                        $tellersContent .= $this->GetNaamAndEmail($teller);
                    }
                    $tellersContent .= $this->GetNewLine();
                }
            }
        }

        $template = file_get_contents("./UseCases/Email/templates/samenvattingTemplate.txt");
        $placeholders = [
            Template::NAAM => $this->scheidsco->naam,
            Template::SCHEIDSRECHTERS => $scheidsrechtersContent,
            Template::TELLERS => $tellersContent,
            Template::ZAALWACHTERS => $zaalwachtersContent,
            Template::BARCIELEDEN => $barcieContent,
        ];
        $body = Email::FillTemplate($template, $placeholders);

        $title = "Samenvatting fluit/tel/zaalwacht mails " . date("j-M-Y");

        return new Email(
            $title,
            $body,
            $receiver
        );
    }

    private function GetNaamAndEmail($persoon)
    {
        return $persoon->naam .  " (" . $persoon->email . ")<br>";
    }

    private function GetBoldHeader($titel)
    {
        return "<b>" . $titel . "<br>";
    }

    private function GetNewLine()
    {
        return "<br>";
    }
}
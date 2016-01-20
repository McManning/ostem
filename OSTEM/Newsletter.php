<?php

namespace OSTEM;

use OSTEM\Listserv;

class Newsletter {
    
    private $id;
    public $subject;
    public $message;
    public $date;
    public $sender;
    public $term;
    public $sent;

    function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Retrieve the draft record from SQL if one exists and 
     * populate ourselves with draft contents.
     *
     * @return boolean true on success
     */
    public function loadDraft()
    {
        $statement = $this->db->prepare("
            SELECT
                id, subject, message, date, sender, term
            FROM
                newsletters
            WHERE
                CAST(sent AS INTEGER) = 0
            ORDER BY 
                date DESC
            LIMIT 1
        ");

        $statement->execute();
        $rows = $statement->fetchAll();

        // TODO: Move this empty set
        if (empty($rows)) {
            $this->id = null;
            $this->subject = '';
            $this->message = '';
            $this->date = new \DateTime();
            $this->term = 'TODO';
            $this->sent = false;
        } 
        else {
            $draft = (object)$rows[0];

            $this->id = $draft->id;
            $this->subject = $draft->subject;
            $this->message = $draft->message;
            $this->date = new \DateTime($draft->date);
            $this->sender = $draft->sender;
            $this->term = $draft->term;
            $this->sent = false;
        }
    }

    /**
     * Save this newsletter to persistent storage as a draft
     *
     * @return boolean true on success
     */
    public function save()
    {
        // Update date to now
        $this->date = new \DateTime();

        if ($this->id) {
            $statement = $this->db->prepare("
                UPDATE 
                    newsletters
                SET 
                    subject=:subject,
                    message=:message,
                    date=:date,
                    sender=:sender,
                    term=:term,
                    sent=:sent
                WHERE
                    id=:id
            ");

            $statement->execute(array(
                'id' => $this->id,
                'subject' => $this->subject,
                'message' => $this->message,
                'date' => $this->date->format('Y-m-d H:i:s'),
                'sender' => $this->sender,
                'term' => $this->term,
                'sent' => $this->sent
            ));
        } else {
            $statement = $this->db->prepare("
                INSERT INTO  
                    newsletters (subject, message, date, sender, term, sent)
                VALUES 
                    (:subject, :message, :date, :sender, :term, :sent) 
            ");

            $statement->execute(array(
                'subject' => $this->subject,
                'message' => $this->message,
                'date' => $this->date->format('Y-m-d H:i:s'),
                'sender' => $this->sender,
                'term' => $this->term,
                'sent' => $this->sent
            ));
        }
    }

    /**
     * Retrieve a list of (5) recently sent newsletters
     */
    public static function getRecent(\PDO $db)
    {
        $recent = array();
        $statement = $db->prepare("
            SELECT
                id, subject, message, date, sender, term
            FROM
                newsletters
            WHERE
                sent = 1
            ORDER BY 
                date DESC
            LIMIT 5
        ");

        $statement->execute();
        $rows = $statement->fetchAll();

        foreach ($rows as $row) {
            $draft = (object)$row;

            $newsletter = new Newsletter($db);
            $newsletter->id = $draft->id;
            $newsletter->subject = $draft->subject;
            $newsletter->message = $draft->message;
            $newsletter->date = new \DateTime($draft->date);
            $newsletter->sender = $draft->sender;
            $newsletter->term = $draft->term;
            $newsletter->sent = true;
            $recent[] = $newsletter;
        }

        return $recent;
    }

    /**
     * Send this newsletter to the mailing list as-is
     *
     * @param \Slim\View $view engine to render the email template
     */ 
    public function send(\Slim\View $view)
    {
        // Retrieve everyone on our listserv
        $listserv = new Listserv($this->db);
        $emails = $listserv->getEmails();
        $today = new \DateTime();

        // Send a personalized email to each person
        foreach ($emails as $email) {

            // Render out our email template
            $body = $view->fetch('newsletter-template.html.j2', array(
                'today' => $today,
                'sender' => $this->sender,
                'subject' => $this->subject,
                'message' => $this->message,
                'uuid' => $email->uuid
            ));

            // TODO: Send to mailing list!
            file_put_contents('cache/email-' . $email->uuid . '.html', $body);
        }

        $this->sent = true;
        $this->save();
    }

}

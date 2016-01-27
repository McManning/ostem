<?php

namespace OSTEM;

use OSTEM\Listserv;
use Psr\Log\LoggerInterface;
use Slim\View;

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
     * Send this newsletter to a specific set of emails.
     *
     * Note that this does not mark the newsletter as sent. If this is to be 
     * fired off to the whole listserv, that should be done in sendToListserv().
     *
     * @param Slim\View $view engine to render the email template
     * @param array $emails array of objects in the form: { uuid: 'str', email: 'str' }
     * @param Psr\Log\LoggerInterface $log target for logging failed emails 
     */
    public function send(View $view, array $emails, LoggerInterface $log)
    {
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

            if (DEBUG_MODE) {
                file_put_contents('cache/email-' . $email->uuid . '.html', $body);
            }
            
            // Configure outbound headers
            $headers = array();
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-type: text/html; charset=iso-8859-1';
            $headers[] = 'From: OSTEM at Ohio State <' . $this->sender . '>';
            $headers[] = 'Subject: ' . $this->subject;
            $headers[] = 'X-Mailer: PHP/' . phpversion();

            // Fire off using PHP mail() (not great for high volume, but tolerable right now)
            if (!@mail($email->email, $this->subject, $body, implode("\r\n", $headers))) {

                // If a message failed to send, log details for later investigation
                $log->warning('Failed to send newsletter to recipient', (object)array(
                    'to' => $email->email,
                    'subject' => $subject
                ));
            };
        }
    }

    /**
     * Send this newsletter to the all of the listserv and mark it as sent
     *
     * @param Slim\View $view engine to render the email template
     * @param OSTEM\Listserv $listserv to send emails to
     * @param Psr\Log\LoggerInterface $log target for logging failed emails 
     */ 
    public function sendToListserv(View $view, Listserv $listserv, LoggerInterface $log)
    {
        // Retrieve everyone on our listserv
        $emails = $listserv->getEmails();

        $this->send($view, $emails, $log);

        // Log it as being sent to the mailing list
        $this->sent = true;
        $this->save();
    }
}

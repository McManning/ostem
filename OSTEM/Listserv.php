<?php

namespace OSTEM;

class Listserv {
    
    /**
     * @var cache for listserv emails
     */
    private $emails = null;

    function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Retrieve a list of all subscribed emails
     */
    public function getEmails()
    {
        if (!$this->emails) {
            $this->emails = array();
            
            $statement = $this->db->prepare("
                SELECT
                    id, uuid, email, date 
                FROM
                    listserv
                ORDER BY 
                    date ASC 
            ");

            $statement->execute();
            $rows = $statement->fetchAll();

            foreach ($rows as $row) {
                $this->emails[] = (object)array(
                    'id' => $row['id'],
                    'uuid' => $row['uuid'],
                    'email' => $row['email'],
                    'date' => new \DateTime($row['date'])
                );
            }
        }

        return $this->emails;
    }

    /**
     * Retrieve an email address associated with a UUID.
     * If it's an invalid UUID, we return a blank string
     * 
     * @param string $uuid
     *
     * @return string
     */
    public function getEmail($uuid) 
    {
        $statement = $this->db->prepare("
            SELECT
                email
            FROM
                listserv
            WHERE
                uuid = :uuid
        ");

        $statement->execute(array(
            'uuid' => $uuid
        ));
        $rows = $statement->fetchAll();

        if (!empty($rows)) {
            return $rows[0]['email'];
        }

        return '';
    }

    /**
     * Returns true if the given email is subscribed already
     *
     * @return boolean
     */
    public function isSubscribed($email)
    {
        $statement = $this->db->prepare("
            SELECT 
                id
            FROM
                listserv
            WHERE
                email = :email
            COLLATE NOCASE
        ");

        $statement->execute(array(
            'email' => $email
        ));

        $rows = $statement->fetchAll();
        return count($rows) > 0;
    }

    /**
     * Add a new subscription email
     */
    public function subscribe($email, $date = null)
    {
        if (!$date) {
            $date = new \DateTime();
        }

        // Only subscribe if we haven't already
        if (!$this->isSubscribed($email)) {

            $statement = $this->db->prepare("
                INSERT INTO 
                    listserv (uuid, email, date)
                VALUES
                    (:uuid, :email, :date)
            ");

            $statement->execute(array(
                'uuid' => str_replace('.', '', uniqid('', true)),
                'email' => $email,
                'date' => $date->format('Y-m-d H:i:s')
            ));
        }
    }

    /**
     * Unsubscribe an email from the listserv.
     * 
     * This method requires you to know the UUID of the email, so that
     * users can't unsubscribe other users just by knowing their email.
     */
    public function unsubscribe($uuid)
    {
        $statement = $this->db->prepare("
            DELETE FROM 
                listserv
            WHERE
                uuid = :uuid
        ");

        $statement->execute(array(
            'uuid' => $uuid
        ));
    }
}

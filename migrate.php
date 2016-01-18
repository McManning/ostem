<?php
/*
    Migration script to move things from the crappy old serialized objects
    format to something database-based so it can ACTUALLY be manipulated.
*/

date_default_timezone_set('America/New_York');

function migrateMailings($db, $filename)
{
    $fin = fopen($filename, 'r');
    $data = fread($fin, filesize($filename));
    fclose($fin);

    $deserialized = unserialize($data);

    $terms = [];
    foreach ($deserialized as $term => $mailings) {
        $terms[$term] = [];
        $mailings = array_reverse($mailings);
        foreach ($mailings as $id => $data) {

            // The list can contain entries without a sender, indicating that
            // those messages haven't actually been sent out and are just drafted
            if (!empty($data['sender'])) {

                // Do some cleanup of message content. Everyone seems to have used
                // different editors to make it all sorts of a mess. So strip out 
                // unwanted HTML, axe multiple newlines, and replace newlines with\
                // clean breaks
                $data['message'] = nl2br(
                    preg_replace(
                        '/[\r\n]{2,}/', 
                        "\n\n", 
                        strip_tags(
                            $data['message'], 
                            '<b><strong>'
                        )
                    )
                );

                // Genius stored it as 201511302307
                $data['date'] = \DateTime::createFromFormat(
                    'YmdHi', 
                    $data['dt']
                );

                print 'Inserting ' . $data['date']->format('m/d/Y') . 
                    ' - ' . $data['subject'] . "\n";
                
                // Push to SQL
                $query = "
                    INSERT INTO 
                        newsletters (subject, message, date, sender, term, sent)
                    VALUES 
                        (:subject, :message, :date, :sender, :term, 1)
                ";

                try {
                    $statement = $db->prepare($query);
                    $statement->execute([
                        'subject' => $data['subject'],
                        'message' => $data['message'],
                        'date' => $data['date']->format('Y-m-d H:i:s'),
                        'sender' => $data['sender'],
                        'term' => $term
                    ]);
                } catch (\PDOException $e) {
                    die(sprintf('DB error: %s', $e->getMessage()));
                }
            }
        }
    }
}

function migrateListserv($db, $filename)
{
    $fin = fopen($filename, 'r');
    $data = fread($fin, filesize($filename));
    fclose($fin);

    $deserialized = unserialize($data);

    $now = new DateTime();
    foreach ($deserialized as $id => $email) {
        print 'Inserting ' . $email . "\n";

        // Push to SQL
        $query = "
            INSERT INTO 
                listserv (uuid, email, date)
            VALUES 
                (:uuid, :email, :date)
        ";

        try {
            $statement = $db->prepare($query);
            $statement->execute([
                'uuid' => str_replace('.', '', uniqid('', true)),
                'email' => $email,
                'date' => $now->format('Y-m-d H:i:s')
            ]);
        } catch (\PDOException $e) {
            die(sprintf('DB error: %s', $e->getMessage()));
        }
    }
}

$db = new \PDO('sqlite:php-data/ostem.db', null, null, [
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
]);

//migrateMailings($db, 'php-data/mailings');
migrateListserv($db, 'php-data/listserv');

/*
    DDLs:

    CREATE TABLE IF NOT EXISTS [newsletters] (
        [id] INTEGER  NOT NULL PRIMARY KEY,
        [subject] TEXT NOT NULL,
        [message] TEXT NOT NULL,
        [date] DATETIME NULL,    
        [sender] TEXT NOT NULL,
        [term] TEXT NOT NULL,
        [sent] INTEGER NOT NULL
    );

    
    CREATE TABLE IF NOT EXISTS [listserv] (
        [id] INTEGER  NOT NULL PRIMARY KEY,
        [uuid] TEXT NOT NULL,
        [email] TEXT NOT NULL,
        [date] DATETIME NULL
    );
    CREATE UNIQUE INDEX IF NOT EXISTS ListservUUIDUnique ON listserv (uuid);

*/
<?php

namespace OSTEM;

/** 
 * Management for listserv messages
 */
class Mailings 
{
    /**
     * @var array
     */
    public $terms;

    /**
     *
     */
    function __construct($filename)
    {
        $this->parseLegacyFile($filename);
    }

    /**
     * Parse out mailing data from the serialized legacy store
     *
     * @param string $filename
     */
    private function parseLegacyFile($filename)
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
                    
                    $terms[$term][] = $data;
                }
            }
        }

        $this->terms = array_reverse($terms);
    }
}

<?php

/**
 * DNS Library for handling lookups and updates. 
 *
 * Copyright (c) 2020, Mike Pultz <mike@mikepultz.com>. All rights reserved.
 *
 * See LICENSE for more details.
 *
 * @category  Networking
 * @package   DNS2
 * @author    Mike Pultz <mike@mikepultz.com>
 * @copyright 2020 Mike Pultz <mike@mikepultz.com>
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link      https://netdns2.com/
 * @since     File available since Release 0.6.0
 *
 */

namespace NetDNS2\RR;

/**
 * AFSDB Resource Record - RFC1183 section 1
 *
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    SUBTYPE                    |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                    HOSTNAME                   /
 *    /                                               /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 */
class AFSDB extends \NetDNS2\RR
{
    /*
     * The AFSDB sub type
     */
    public $subtype;

    /*
     * The AFSDB hostname
     */
    public $hostname;

    /**
     * method to return the rdata portion of the packet as a string
     *
     * @return  string
     * @access  protected
     *
     */
    protected function rrToString()
    {
        return $this->subtype . ' ' . $this->cleanString($this->hostname) . '.';
    }

    /**
     * parses the rdata portion from a standard DNS config line
     *
     * @param array $rdata a string split line of values for the rdata
     *
     * @return boolean
     * @access protected
     *
     */
    protected function rrFromString(array $rdata)
    {
        $this->subtype  = array_shift($rdata);
        $this->hostname = $this->cleanString(array_shift($rdata));

        return true;
    }

    /**
     * parses the rdata of the \NetDNS2\Packet object
     *
     * @param \NetDNS2\Packet &$packet a \NetDNS2\Packet packet to parse the RR from
     *
     * @return boolean
     * @access protected
     *
     */
    protected function rrSet(\NetDNS2\Packet &$packet)
    {
        if ($this->rdlength > 0) {
            
            //
            // unpack the subtype
            //
            $x = unpack('nsubtype', $this->rdata);

            $this->subtype  = $x['subtype'];
            $offset         = $packet->offset + 2;

            $this->hostname = \NetDNS2\Packet::expand($packet, $offset);

            return true;
        }

        return false;
    }

    /**
     * returns the rdata portion of the DNS packet
     *
     * @param \NetDNS2\Packet &$packet a \NetDNS2\Packet packet use for
     *                                 compressed names
     *
     * @return mixed                   either returns a binary packed
     *                                 string or null on failure
     * @access protected
     *
     */
    protected function rrGet(\NetDNS2\Packet &$packet)
    {
        if (strlen($this->hostname) > 0) {
            
            $data = pack('n', $this->subtype);
            $packet->offset += 2;
            
            $data .= $packet->compress($this->hostname, $packet->offset);

            return $data;
        }
        
        return null; 
    }
}

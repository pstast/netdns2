<?php

/**
 * DNS Library for handling lookups and updates. 
 *
 * Copyright (c) 2020, Mike Pultz <mike@mikepultz.com>. All rights reserved.
 *
 * See LICENSE for more details.
 *
 * @category  Networking
 * @package   NetDNS2
 * @author    Mike Pultz <mike@mikepultz.com>
 * @copyright 2020 Mike Pultz <mike@mikepultz.com>
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link      https://netdns2.com/
 * @since     File available since Release 0.6.0
 *
 */

namespace NetDNS2\RR;

/**
 * SOA Resource Record - RFC1035 section 3.3.13
 *
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                     MNAME                     /
 *    /                                               /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                     RNAME                     /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    SERIAL                     |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    REFRESH                    |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                     RETRY                     |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    EXPIRE                     |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    MINIMUM                    |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 */
class SOA extends \NetDNS2\RR
{
    /*
     * The master DNS server
     */
    public $mname;

    /*
     * mailbox of the responsible person
     */
    public $rname;

    /*
     * serial number
      */
    public $serial;

    /*
      * refresh time
      */
    public $refresh;

    /*
      * retry interval
     */
    public $retry;

    /*
     * expire time
      */
    public $expire;

    /*
     * minimum TTL for any RR in this zone
      */
    public $minimum;

    /**
     * method to return the rdata portion of the packet as a string
     *
     * @return  string
     * @access  protected
     *
     */
    protected function rrToString()
    {
        return $this->cleanString($this->mname) . '. ' . 
            $this->cleanString($this->rname) . '. ' . 
            $this->serial . ' ' . $this->refresh . ' ' . $this->retry . ' ' . 
            $this->expire . ' ' . $this->minimum;
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
        $this->mname    = $this->cleanString($rdata[0]);
        $this->rname    = $this->cleanString($rdata[1]);

        $this->serial   = $rdata[2];
        $this->refresh  = $rdata[3];
        $this->retry    = $rdata[4];
        $this->expire   = $rdata[5];
        $this->minimum  = $rdata[6];

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
            // parse the 
            //
            $offset = $packet->offset;

            $this->mname = \NetDNS2\Packet::expand($packet, $offset);
            $this->rname = \NetDNS2\Packet::expand($packet, $offset, true);

            //
            // get the SOA values
            //
            $x = unpack(
                '@' . $offset . '/Nserial/Nrefresh/Nretry/Nexpire/Nminimum/', 
                $packet->rdata
            );

            $this->serial   = \NetDNS2\Client::expandUint32($x['serial']);
            $this->refresh  = \NetDNS2\Client::expandUint32($x['refresh']);
            $this->retry    = \NetDNS2\Client::expandUint32($x['retry']);
            $this->expire   = \NetDNS2\Client::expandUint32($x['expire']);
            $this->minimum  = \NetDNS2\Client::expandUint32($x['minimum']);

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
        if (strlen($this->mname) > 0) {
    
            $data = $packet->compress($this->mname, $packet->offset);
            $data .= $packet->compress($this->rname, $packet->offset);

            $data .= pack(
                'N5', $this->serial, $this->refresh, $this->retry, 
                $this->expire, $this->minimum
            );

            $packet->offset += 20;

            return $data;
        }

        return null;
    }
}

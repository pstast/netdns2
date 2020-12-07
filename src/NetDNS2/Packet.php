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
 * This file contains code based off the Net::DNS Perl module by Michael Fuhr.
 *
 * This is the copyright notice from the PERL Net::DNS module:
 *
 * Copyright (c) 1997-2000 Michael Fuhr.  All rights reserved.  This program is 
 * free software; you can redistribute it and/or modify it under the same terms 
 * as Perl itself.
 *
 */

namespace NetDNS2;

/**
 * This is the base class that holds a standard DNS packet.
 *
 * The \NetDNS2\Packet\Request and \NetDNS2\Packet\Response classes extend this
 * class.
 *
 */
class Packet
{
    /*
     * the full binary data and length for this packet
     */
    public $rdata;
    public $rdlength;

    /*
     * the offset pointer used when building/parsing packets
     */
    public $offset = 0;

    /*
     * \NetDNS2\Header object with the DNS packet header
     */
    public $header;

    /*
     * array of \NetDNS2\Question objects
     *
     * used as "zone" for updates per RFC2136
     *
     */
    public $question = [];

    /*
     * array of \NetDNS2\RR Objects for Answers
     * 
     * used as "prerequisite" for updates per RFC2136
     *
     */
    public $answer = [];

    /*
     * array of \NetDNS2\RR Objects for Authority
     *
     * used as "update" for updates per RFC2136
     *
     */
    public $authority = [];

    /*
     * array of \NetDNS2\RR Objects for Addtitional
     */
    public $additional = [];

    /*
     * array of compressed labeles
     */
    private $_compressed = [];

    /**
     * magic __toString() method to return the \NetDNS2\Packet as a string
     *
     * @return string
     * @access public
     *
     */
    public function __toString()
    {
        $output = $this->header->__toString();

        foreach($this->question as $x)
        {
            $output .= $x->__toString() . "\n";
        }
        foreach($this->answer as $x)
        {
            $output .= $x->__toString() . "\n";
        }
        foreach($this->authority as $x)
        {
            $output .= $x->__toString() . "\n";
        }
        foreach($this->additional as $x)
        {
            $output .= $x->__toString() . "\n";
        }

        return $output;
    }

    /**
     * returns a full binary DNS packet
     *
     * @return string
     * @throws \NetDNS2\Exception
     * @access public
     *
     */
    public function get()
    {
        $data = $this->header->get($this);

        foreach($this->question as $x) 
        {
            $data .= $x->get($this);
        }
        foreach($this->answer as $x)
        {
            $data .= $x->get($this);
        }
        foreach($this->authority as $x)
        {
            $data .= $x->get($this);
        }
        foreach($this->additional as $x)
        {
            $data .= $x->get($this);
        }

        return $data;
    }

    /**
     * applies a standard DNS name compression on the given name/offset
     *
     * This logic was based on the Net::DNS::Packet::dn_comp() function 
     * by Michanel Fuhr
     *
     * @param string  $name    the name to be compressed
     * @param integer &$offset the offset into the given packet object
     *
     * @return string
     * @access public
     *
     */
    public function compress($name, &$offset)
    {
        //
        // we're using preg_split() rather than explode() so that we can use the negative lookbehind,
        // to catch cases where we have escaped dots in strings.
        //
        // there's only a few cases like this- the rname in SOA for example
        //
        $names      = str_replace('\.', '.', preg_split('/(?<!\\\)\./', $name));
        $compname   = '';

        while(empty($names) == false)
        {
            $dname = join('.', $names);

            if (isset($this->_compressed[$dname]) == true)
            {
                $compname .= pack('n', 0xc000 | $this->_compressed[$dname]);
                $offset += 2;

                break;
            }

            $this->_compressed[$dname] = $offset;

            $first = array_shift($names);

            $length = strlen($first);
            if ($length <= 0)
            {
                continue;
            }
        
            //
            // truncate see RFC1035 2.3.1
            //
            if ($length > 63)
            {
                $length = 63;
                $first = substr($first, 0, $length);
            }

            $compname .= pack('Ca*', $length, $first);
            $offset += $length + 1;
        }

        if (empty($names) == true)
        {
            $compname .= pack('C', 0);
            $offset++;
        }

        return $compname;
    }

    /**
     * applies a standard DNS name compression on the given name/offset
     *
     * This logic was based on the Net::DNS::Packet::dn_comp() function 
     * by Michanel Fuhr
     *
     * @param string $name the name to be compressed
     *
     * @return string
     * @access public
     *
     */
    public static function pack($name)
    {
        $offset = 0;
        $names = explode('.', $name);
        $compname = '';

        while(empty($names) == false)
        {
            $first = array_shift($names);
            $length = strlen($first);

            $compname .= pack('Ca*', $length, $first);
            $offset += $length + 1;
        }

        $compname .= "\0";
        $offset++;

        return $compname;
    }

    /**
     * expands the domain name stored at a given offset in a DNS Packet
     *
     * This logic was based on the Net::DNS::Packet::dn_expand() function
     * by Michanel Fuhr
     *
     * @param \NetDNS2\Packet &$packet the DNS packet to look in for the domain name
     * @param integer         &$offset the offset into the given packet object
     * @param boolean         $escape_dot_literals if we should escape periods in names
     *
     * @return mixed either the domain name or null if it's not found.
     * @access public
     *
     */
    public static function expand(\NetDNS2\Packet &$packet, &$offset, $escape_dot_literals = false)
    {
        $name = '';

        while(1)
        {
            if ($packet->rdlength < ($offset + 1))
            {
                return null;
            }
            
            $xlen = ord($packet->rdata[$offset]);
            if ($xlen == 0)
            {
                ++$offset;
                break;

            } else if (($xlen & 0xc0) == 0xc0)
            {
                if ($packet->rdlength < ($offset + 2))
                {
                    return null;
                }

                $ptr = ord($packet->rdata[$offset]) << 8 | ord($packet->rdata[$offset+1]);
                $ptr = $ptr & 0x3fff;

                $name2 = \NetDNS2\Packet::expand($packet, $ptr);
                if (is_null($name2) == true)
                {
                    return null;
                }

                $name .= $name2;
                $offset += 2;
    
                break;
            } else
            {
                ++$offset;

                if ($packet->rdlength < ($offset + $xlen))
                {
                    return null;
                }

                $elem = '';
                $elem = substr($packet->rdata, $offset, $xlen);

                //
                // escape literal dots in certain cases (SOA rname)
                //
                if ( ($escape_dot_literals == true) && (strpos($elem, '.') !== false) )
                {
                    $elem = str_replace('.', '\.', $elem);
                }

                $name .= $elem . '.';
                $offset += $xlen;
            }
        }

        return trim($name, '.');
    }

    /**
     * parses a domain label from a DNS Packet at the given offset
     *
     * @param \NetDNS2\Packet &$packet the DNS packet to look in for the domain name
     * @param integer         &$offset the offset into the given packet object
     *
     * @return mixed either the domain name or null if it's not found.
     * @access public
     *
     */
    public static function label(\NetDNS2\Packet &$packet, &$offset)
    {
        $name = '';

        if ($packet->rdlength < ($offset + 1))
        {
            return null;
        }

        $xlen = ord($packet->rdata[$offset]);
        ++$offset;

        if (($xlen + $offset) > $packet->rdlength)
        {
            $name = substr($packet->rdata, $offset);
            $offset = $packet->rdlength;
        } else
        {
            $name = substr($packet->rdata, $offset, $xlen);
            $offset += $xlen;
        }

        return $name;
    }

    /**
     * copies the contents of the given packet, to the local packet object. this
     * function intentionally ignores some of the packet data.
     *
     * @param \NetDNS2\Packet $packet the DNS packet to copy the data from
     *
     * @return boolean
     * @access public
     *
     */
    public function copy(\NetDNS2\Packet $packet)
    {
        $this->header       = $packet->header;
        $this->question     = $packet->question;
        $this->answer       = $packet->answer;
        $this->authority    = $packet->authority;
        $this->additional   = $packet->additional;

        return true;
    }

    /**
     * resets the values in the current packet object
     *
     * @return boolean
     * @access public
     *
     */
    public function reset()
    {
        $this->header->id   = $this->header->nextPacketId();
        $this->rdata        = '';
        $this->rdlength     = 0;
        $this->offset       = 0;
        $this->answer       = [];
        $this->authority    = [];
        $this->additional   = [];
        $this->_compressed  = [];
    
        return true;
    }
}
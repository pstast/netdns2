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
 * NSAP Resource Record - RFC1706
 *
 *             |--------------|
 *             | <-- IDP -->  |
 *             |--------------|-------------------------------------|
 *             | AFI |  IDI   |            <-- DSP -->              |
 *             |-----|--------|-------------------------------------|
 *             | 47  |  0005  | DFI | AA |Rsvd | RD |Area | ID |Sel |
 *             |-----|--------|-----|----|-----|----|-----|----|----|
 *      octets |  1  |   2    |  1  | 3  |  2  | 2  |  2  | 6  | 1  |
 *             |-----|--------|-----|----|-----|----|-----|----|----|
 * 
 */
class NSAP extends \NetDNS2\RR
{
    public $afi;
    public $idi;
    public $dfi;
    public $aa;
    public $rsvd;
    public $rd;
    public $area;
    public $id;
    public $sel;

    /**
     * method to return the rdata portion of the packet as a string
     *
     * @return  string
     * @access  protected
     *
     */
    protected function rrToString()
    {
        return $this->cleanString($this->afi) . '.' . 
            $this->cleanString($this->idi) . '.' . 
            $this->cleanString($this->dfi) . '.' . 
            $this->cleanString($this->aa) . '.' . 
            $this->cleanString($this->rsvd) . '.' . 
            $this->cleanString($this->rd) . '.' . 
            $this->cleanString($this->area) . '.' . 
            $this->cleanString($this->id) . '.' . 
            $this->sel;
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
        $data = strtolower(trim(array_shift($rdata)));

        //
        // there is no real standard for format, so we can't rely on the fact that
        // the value will come in with periods separating the values- so strip 
        // them out if they're included, and parse without them.
        //    
        $data = str_replace([ '.', '0x' ], '', $data);

        //
        // unpack it as ascii characters
        //
        $x = unpack('A2afi/A4idi/A2dfi/A6aa/A4rsvd/A4rd/A4area/A12id/A2sel', $data);
        
        //
        // make sure the afi value is 47
        //
        if ($x['afi'] == '47') {

            $this->afi  = '0x' . $x['afi'];
            $this->idi  = $x['idi'];
            $this->dfi  = $x['dfi'];
            $this->aa   = $x['aa'];
            $this->rsvd = $x['rsvd'];
            $this->rd   = $x['rd'];
            $this->area = $x['area'];
            $this->id   = $x['id'];
            $this->sel  = $x['sel'];

            return true;
        }

        return false;
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
        if ($this->rdlength == 20) {

            //
            // get the AFI value
            //
            $this->afi = dechex(ord($this->rdata[0]));

            //
            // we only support AFI 47- there arent' any others defined.
            //
            if ($this->afi == '47') {

                //
                // unpack the rest of the values
                //
                $x = unpack(
                    'Cafi/nidi/Cdfi/C3aa/nrsvd/nrd/narea/Nidh/nidl/Csel', 
                    $this->rdata
                );

                $this->afi  = sprintf('0x%02x', $x['afi']);
                $this->idi  = sprintf('%04x', $x['idi']);
                $this->dfi  = sprintf('%02x', $x['dfi']);
                $this->aa   = sprintf(
                    '%06x', $x['aa1'] << 16 | $x['aa2'] << 8 | $x['aa3']
                );
                $this->rsvd = sprintf('%04x', $x['rsvd']);
                $this->rd   = sprintf('%04x', $x['rd']);
                $this->area = sprintf('%04x', $x['area']);
                $this->id   = sprintf('%08x', $x['idh']) . 
                    sprintf('%04x', $x['idl']);
                $this->sel  = sprintf('%02x', $x['sel']);

                return true;
            }
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
        if ($this->afi == '0x47') {

            //
            // build the aa field
            //
            $aa = unpack('A2x/A2y/A2z', $this->aa);

            //
            // build the id field
            //
            $id = unpack('A8a/A4b', $this->id);

            //
            $data = pack(
                'CnCCCCnnnNnC', 
                hexdec($this->afi), 
                hexdec($this->idi),
                hexdec($this->dfi),
                hexdec($aa['x']),
                hexdec($aa['y']),
                hexdec($aa['z']),
                hexdec($this->rsvd),
                hexdec($this->rd),
                hexdec($this->area),
                hexdec($id['a']),
                hexdec($id['b']),
                hexdec($this->sel)
            );

            if (strlen($data) == 20) {
                
                $packet->offset += 20;
                return $data;
            }
        }

        return null;
    }
}

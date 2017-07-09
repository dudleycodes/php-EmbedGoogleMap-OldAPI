<?php
if ( $_SERVER['SCRIPT_FILENAME'] == __FILE__ )  {   die('Direct access to this page denied');   }

CLASS GOOGLEMAP
{
    private $apiKey = null, $name = null;
    private $center = array(), $height = null, $width = null, $validCordinates = array(), $zoom = null;

    public function __construct( $key )
    {
        $this->apiKey = $key;
        $this->name = 'googlemap-'. rand( 11, 999999 );
    }

    public function addAddress( $address = null, $city = null, $zip = null, $country = 'United States of America' )
    {
        if ( is_null($address) OR is_null($city) OR is_null($zip) )
        {
            $debug->notice( 'An improper address has been passed to class.googlemap' );
            return false;
        }
        else
        {
            $result = $this->geocode($address, $city, $zip, $country );

            if ( $result == FALSE )
            {
                $debug->warn( 'class.googlemap > addAddress > geocoding returned false' );
                return false;
            }
            else
            {
                $this->addCordinates( $result[0], $result[1] );
                unset($result);
            }
        }
    }

    public function addCords( $latitude, $longitude, $html = NULL )
    {
        $this->addCordinates( $latitude, $longitude, $html );
    }

    public function addCordinates( $latitude, $longitude, $html = null )
    {
        if ( $latitude < -90 OR $latitude > 90)
        {
            $debug->warn( 'class.googlemap > addCordinates > provided latitude is outside valid ranges' );
            return false;
        }
        elseif ( $longitude < -180 OR $longitude > 180 )
        {
            $debug->warn( 'class.googlemap > addCordinates > provided longitude is outside valid ranges' );
            return false;
        }
        else
        {
            $index = count( $this->validCordinates );
            $this->validCordinates[$index]['latitude'] = $latitude;
            $this->validCordinates[$index]['longitude'] = $longitude;
            $this->validCordinates[$index]['html'] = $html;
        }
    }

    public function center( $latitude = null, $longitude =null )
    {
        $c = count( $this->validCordinates );
        if ( $c > 0 )
        {
            $x = array();	$y = array();

            for ( $i = 0; $i < $c; $i++ )
            {
                $x[] = $this->validCordinates[$i]['longitude'] + 180;
                $y[] = $this->validCordinates[$i]['latitude'] + 90;
            }
                $longitude = ( array_sum( $x ) / $c ) - 180;
                $latitude = ( array_sum( $y ) / $c ) - 90;

                unset( $x );	unset( $y );
        }
        else
        {
            $longitude = 0;
            $latitude = 0;
        }
        $this->center = array( $latitude, $longitude, 'calculated-average' );
    }

    public function display()
    {   global $dom;

        $dom->addResource( 'maps.google.com/maps?file=api&amp;v=2&amp;key='. $this->apiKey .'&sensor=false' );
        if ( empty($this->center) )		$this->center();
        if ( is_null($this->height) )	$this->height(350);
        if ( is_null($this->width) )	$this->width(400);
        if ( is_null($this->zoom) )		$this->zoom( 10 );
        echo '<div id="'. $this->name .'" style="width:'. $this->width .'px; height:'. $this->height .'px"></div>';
        echo '<script type="text/javascript">';
        echo "\n";
        echo '	if (GBrowserIsCompatible())
                {
                    function createMarker(point, html)
                    {
                        var marker = new GMarker(point);
                        GEvent.addListener( marker, "click", function(){ map.openInfoWindowHtml( point, html )	} );
                        return marker;
                    }

                    var map = new GMap2(document.getElementById("'. $this->name .'"));
                    map.setCenter( new GLatLng( '. $this->center[0] .', '. $this->center[1] .' ), '. $this->zoom .' );
                    map.setUIToDefault();
                    ';

        $c = count( $this->validCordinates );
        for ( $i = 0; $i < $c; $i++ )
        {
            $html = $this->validCordinates[$i]['html'];
            $latitude = $this->validCordinates[$i]['latitude'];
            $longitude = $this->validCordinates[$i]['longitude'];

            echo "	var point = new GLatLng( $latitude, $longitude );	\n
                        map.addOverlay( createMarker( point, \"$html\" ) );		\n ";
        }

        echo '}';
        echo "</script>\n";

    }

    public function geocode( $address = null, $city = null, $zip = null, $country = 'United States' )
    {
        if ( is_null($this->apiKey) )
        {
            $debug->warn( 'A Google API key must be provided!' );
            return false;
        }

        $url = $address .','. $zip .'+'. $city .','. $country;
        $url = str_replace( ' ', '_', $url );
        $url = 'http://maps.google.com/maps/geo?q=$'. $url .'&output=csv&key='. $this->apiKey;

        $handle = curl_init();
        curl_setopt( $handle, CURLOPT_URL, $url );
        curl_setopt( $handle, CURLOPT_HEADER, 0 );
        curl_setopt( $handle, CURLOPT_USERAGENT, $_SERVER["HTTP_USER_AGENT"] );
        curl_setopt( $handle, CURLOPT_FOLLOWLOCATION, 1 );
        curl_setopt( $handle, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec( $handle );
        $result = explode( ',', $result );
        curl_close( $handle );

        if ( $result[0] != '200' )
        {
            switch ( $result[0] )
            {
                case 400:	$msg = 'Bad request';   break;
                case 500:	$msg = 'Server error';  break;
                case 601:	$msg = 'Missing query'; break;
                case 602:	$msg = 'Unknown address';   break;
                case 603:	$msg = 'Unavailable address';   break;
                case 610:	$msg = 'An invalid APIkey was specified';   break;
                case 620:	$msg = 'Too many queries have been performed using the given API key';	break;
                default:	$msg = 'Returned status code definition is unknown';
            }
            echo "class.googlemap > geocode > Geocoding failed > received status: '". $result[0] ."' - ". $msg;
            return false;
        }
        elseif ( $result[1] == 0 )
        {
            $debug->warn( "class.googlemap > geocode > Geocoding failed > Accuracy is unknown" );
            return false;
        }
        elseif ( $result[2] < -90 OR $result[2] > 90 )
        {
            $debug->warn( "class.googlemap > geocode > Geocoding failed > Return latitude was outside valid ranges" );
            return false;
        }
        elseif ( $result[3] < -180 OR $result[3] > 180)
        {
            $debug->warn( "class.googlemap > geocode > Geocoding failed > Return longitude was outside valid ranges" );
            return false;
        }
        else
        {
            switch ( $result[1] )
            {
                case 1:	$accuracy = 'Country';		break;
                case 2:	$accuracy = 'Region';		break;
                case 3:	$accuracy = 'Sub-Region';	break;
                case 4:	$accuracy = 'Town';			break;
                case 5:	$accuracy = 'Postcode';		break;
                case 6:	$accuracy = 'Street';		break;
                case 7:	$accuracy = 'Intersection';	break;
                case 8:	$accuracy = 'Address';		break;
                default:$accuracy = 'Unknown';
            }
            return array( $result[2], $result[3], $accuracy );
        }
    }

    public function height( $height = null )
    {
        if ( is_null($height) )
        {
            return $this->height;
        }
        elseif ( is_integer($height) AND $height >= 50 )
        {
            $this->height = $height;
            return true;
        }
        else
        {
            return false;
        }
    }

    public function width( $width = null )
    {
        if ( is_null($width) )
        {
            return $this->width;
        }
        elseif( is_integer($width) AND $width >= 50 )
        {
            $this->width = $width;
            return true;
        }
        else
        {
            return false;
        }
    }

	public function zoom( $zoom = null )
	{
            if ( is_null($zoom) )
            {
                return $this->zoom;
            }
            elseif( $zoom >= 0 AND $zoom <= 19 )
            {
                $this->zoom = $zoom;
            }
            else
            {
                return false;
            }
	}
}

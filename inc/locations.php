<?php
/**
 * Location helper
 *
 * @package WordPressZeroSpam
 * @since 4.0.0
 */

/**
 * Returns the human-readable country or region name.
 *
 * * If no region is provided, it returns the country name.
 * * If the country’s name isn’t available, it returns the country abbreviation.
 * * If a region is provided, it returns the region name.
 * * If the region name isn’t available, it returns the region abbreviation.
 *
 * @link https://en.wikipedia.org/wiki/ISO_3166-2:COUNTRY_CODE
 * @param string $country_code The two letter country code.
 * @param string $region_code  The two letter region code.
 */
if ( ! function_exists( 'wpzerospam_get_location' ) ) {
  function wpzerospam_get_location( $country_code, $region_code = false ) {
    $locations = [
      'US' => [
        'name'    => 'United States',
        'regions' => [
          'AK' => [
            'name' => 'Alaska',
          ],
          'AZ' => [
            'name' => 'Arizona',
          ],
          'AR' => [
            'name' => 'Arkansas',
          ],
          'CA' => [
            'name' => 'California',
          ],
          'CO' => [
            'name' => 'Colorado',
          ],
          'CT' => [
            'name' => 'Connecticut',
          ],
          'DE' => [
            'name' => 'Delaware',
          ],
          'DC' => [
            'name' => 'District Of Columbia',
          ],
          'FL' => [
            'name' => 'Florida',
          ],
          'GA' => [
            'name' => 'Georgia',
          ],
          'HI' => [
            'name' => 'Hawaii',
          ],
          'ID' => [
            'name' => 'Idaho',
          ],
          'IL' => [
            'name' => 'Illinois',
          ],
          'IN' => [
            'name' => 'Indiana',
          ],
          'IA' => [
            'name' => 'Iowa',
          ],
          'KS' => [
            'name' => 'Kansas',
          ],
          'KY' => [
            'name' => 'Kentucky',
          ],
          'LA' => [
            'name' => 'Louisiana',
          ],
          'ME' => [
            'name' => 'Maine',
          ],
          'MD' => [
            'name' => 'Maryland',
          ],
          'MA' => [
            'name' => 'Massachusetts',
          ],
          'MI' => [
            'name' => 'Michigan',
          ],
          'MN' => [
            'name' => 'Minnesota',
          ],
          'MS' => [
            'name' => 'Mississippi',
          ],
          'MO' => [
            'name' => 'Missouri',
          ],
          'MT' => [
            'name' => 'Montana',
          ],
          'NE' => [
            'name' => 'Nebraska',
          ],
          'NV' => [
            'name' => 'Nevada',
          ],
          'NH' => [
            'name' => 'New Hampshire',
          ],
          'NJ' => [
            'name' => 'New Jersey',
          ],
          'NM' => [
            'name' => 'New Mexico',
          ],
          'NY' => [
            'name' => 'New York',
          ],
          'NC' => [
            'name' => 'North Carolina',
          ],
          'ND' => [
            'name' => 'North Dakota',
          ],
          'OH' => [
            'name' => 'Ohio',
          ],
          'OK' => [
            'name' => 'Oklahoma',
          ],
          'OR' => [
            'name' => 'Oregon',
          ],
          'PA' => [
            'name' => 'Pennsylvania',
          ],
          'RI' => [
            'name' => 'Rhode Island',
          ],
          'SC' => [
            'name' => 'South Carolina',
          ],
          'SD' => [
            'name' => 'South Dakota',
          ],
          'TN' => [
            'name' => 'Tennessee',
          ],
          'TX' => [
            'name' => 'Texas',
          ],
          'UT' => [
            'name' => 'Utah',
          ],
          'VT' => [
            'name' => 'Vermont',
          ],
          'VA' => [
            'name' => 'Virginia',
          ],
          'WA' => [
            'name' => 'Washington',
          ],
          'WV' => [
            'name' => 'West Virginia',
          ],
          'WI' => [
            'name' => 'Wisconsin',
          ],
          'WY' => [
            'name' => 'Wyoming'
          ]
        ]
      ],
      'AF' => [
        'name' => 'Afghanistan',
        'regions' => []
      ],
      'AX' => [
        'name' => 'Aland Islands',
        'regions' => []
      ],
      'AL' => [
        'name' => 'Albania',
        'regions' => []
      ],
      'DZ' => [
        'name' => 'Algeria',
        'regions' => []
      ],
      'AS' => [
        'name' => 'American Samoa',
        'regions' => []
      ],
      'AD' => [
        'name' => 'Andorra',
        'regions' => []
      ],
      'AO' => [
        'name' => 'Angola',
        'regions' => []
      ],
      'AI' => [
        'name' => 'Anguilla',
        'regions' => []
      ],
      'AQ' => [
        'name' => 'Antarctica',
        'regions' => []
      ],
      'AG' => [
        'name' => 'Antigua And Barbuda',
        'regions' => []
      ],
      'AR' => [
        'name' => 'Argentina',
        'regions' => []
      ],
      'AM' => [
        'name' => 'Armenia',
        'regions' => []
      ],
      'AW' => [
        'name' => 'Aruba',
        'regions' => []
      ],
      'AU' => [
        'name' => 'Australia',
        'regions' => []
      ],
      'AT' => [
        'name' => 'Austria',
        'regions' => []
      ],
      'AZ' => [
        'name' => 'Azerbaijan',
        'regions' => []
      ],
      'BS' => [
        'name' => 'Bahamas',
        'regions' => []
      ],
      'BH' => [
        'name' => 'Bahrain',
        'regions' => []
      ],
      'BD' => [
        'name' => 'Bangladesh',
        'regions' => []
      ],
      'BB' => [
        'name' => 'Barbados',
        'regions' => []
      ],
      'BY' => [
        'name' => 'Belarus',
        'regions' => []
      ],
      'BE' => [
        'name' => 'Belgium',
        'regions' => []
      ],
      'BZ' => [
        'name' => 'Belize',
        'regions' => []
      ],
      'BJ' => [
        'name' => 'Benin',
        'regions' => []
      ],
      'BM' => [
        'name' => 'Bermuda',
        'regions' => []
      ],
      'BT' => [
        'name' => 'Bhutan',
        'regions' => []
      ],
      'BO' => [
        'name' => 'Bolivia',
        'regions' => []
      ],
      'BA' => [
        'name' => 'Bosnia And Herzegovina',
        'regions' => []
      ],
      'BW' => [
        'name' => 'Botswana',
        'regions' => []
      ],
      'BV' => [
        'name' => 'Bouvet Island',
        'regions' => []
      ],
      'BR' => [
        'name' => 'Brazil',
        'regions' => []
      ],
      'IO' => [
        'name' => 'British Indian Ocean Territory',
        'regions' => []
      ],
      'BN' => [
        'name' => 'Brunei Darussalam',
        'regions' => []
      ],
      'BG' => [
        'name' => 'Bulgaria',
        'regions' => []
      ],
      'BF' => [
        'name' => 'Burkina Faso',
        'regions' => []
      ],
      'BI' => [
        'name' => 'Burundi',
        'regions' => []
      ],
      'KH' => [
        'name' => 'Cambodia',
        'regions' => []
      ],
      'CM' => [
        'name' => 'Cameroon',
        'regions' => []
      ],
      'CA' => [
        'name' => 'Canada',
        'regions' => [
          'AB' => [
            'name' => 'Alberta'
          ],
          'BC' => [
            'name' => 'British Columbia'
          ],
          'MB' => [
            'name' => 'Manitoba'
          ],
          'NB' => [
            'name' => 'New Brunswick'
          ],
          'NL' => [
            'name' => 'Newfoundland and Labrador'
          ],
          'NS' => [
            'name' => 'Nova Scotia'
          ],
          'ON' => [
            'name' => 'Ontario'
          ],
          'PE' => [
            'name' => 'Prince Edward Island'
          ],
          'QC' => [
            'name' => 'Quebec'
          ],
          'SK' => [
            'name' => 'Saskatchewan'
          ],
          'NT' => [
            'name' => 'Northwest Territories'
          ],
          'NU' => [
            'name' => 'Nunavut'
          ],
          'YT' => [
            'name' => 'Yukon'
          ]
        ]
      ],
      'CV' => [
        'name' => 'Cape Verde',
        'regions' => []
      ],
      'KY' => [
        'name' => 'Cayman Islands',
        'regions' => []
      ],
      'CF' => [
        'name' => 'Central African Republic',
        'regions' => []
      ],
      'TD' => [
        'name' => 'Chad',
        'regions' => []
      ],
      'CL' => [
        'name' => 'Chile',
        'regions' => []
      ],
      'CN' => [
        'name' => 'China',
        'regions' => []
      ],
      'CX' => [
        'name' => 'Christmas Island',
        'regions' => []
      ],
      'CC' => [
        'name' => 'Cocos (Keeling) Islands',
        'regions' => []
      ],
      'CO' => [
        'name' => 'Colombia',
        'regions' => []
      ],
      'KM' => [
        'name' => 'Comoros',
        'regions' => []
      ],
      'CG' => [
        'name' => 'Congo',
        'regions' => []
      ],
      'CD' => [
        'name' => 'Congo, Democratic Republic',
        'regions' => []
      ],
      'CK' => [
        'name' => 'Cook Islands',
        'regions' => []
      ],
      'CR' => [
        'name' => 'Costa Rica',
        'regions' => []
      ],
      'CI' => [
        'name' => 'Cote D\'Ivoire',
        'regions' => []
      ],
      'HR' => [
        'name' => 'Croatia',
        'regions' => []
      ],
      'CU' => [
        'name' => 'Cuba',
        'regions' => []
      ],
      'CY' => [
        'name' => 'Cyprus',
        'regions' => []
      ],
      'CZ' => [
        'name' => 'Czech Republic',
        'regions' => []
      ],
      'DK' => [
        'name' => 'Denmark',
        'regions' => []
      ],
      'DJ' => [
        'name' => 'Djibouti',
        'regions' => []
      ],
      'DM' => [
        'name' => 'Dominica',
        'regions' => []
      ],
      'DO' => [
        'name' => 'Dominican Republic',
        'regions' => []
      ],
      'EC' => [
        'name' => 'Ecuador',
        'regions' => []
      ],
      'EG' => [
        'name' => 'Egypt',
        'regions' => []
      ],
      'SV' => [
        'name' => 'El Salvador',
        'regions' => []
      ],
      'GQ' => [
        'name' => 'Equatorial Guinea',
        'regions' => []
      ],
      'ER' => [
        'name' => 'Eritrea',
        'regions' => []
      ],
      'EE' => [
        'name' => 'Estonia',
        'regions' => []
      ],
      'ET' => [
        'name' => 'Ethiopia',
        'regions' => []
      ],
      'FK' => [
        'name' => 'Falkland Islands (Malvinas)',
        'regions' => []
      ],
      'FO' => [
        'name' => 'Faroe Islands',
        'regions' => []
      ],
      'FJ' => [
        'name' => 'Fiji',
        'regions' => []
      ],
      'FI' => [
        'name' => 'Finland',
        'regions' => []
      ],
      'FR' => [
        'name' => 'France',
        'regions' => [
          'ARA' => [
            'name' => 'Auvergne-Rhône-Alpes'
          ],
          'BFC' => [
            'name' => 'Bourgogne-Franche-Comté'
          ],
          'BRE' => [
            'name' => 'Bretagne'
          ],
          'CVL' => [
            'name' => 'Centre-Val de Loire'
          ],
          'COR' => [
            'name' => 'Corse'
          ],
          'GES' => [
            'name' => 'Grand-Est'
          ],
          'GUA' => [
            'name' => 'Guadeloupe'
          ],
          'HDF' => [
            'name' => 'Hauts-de-France'
          ],
          'IDF' => [
            'name' => 'Île-de-France'
          ],
          'MAY' => [
            'name' => 'Mayotte'
          ],
          'NOR' => [
            'name' => 'Normandie'
          ],
          'NAQ' => [
            'name' => 'Nouvelle-Aquitaine'
          ],
          'OCC' => [
            'name' => 'Occitanie'
          ],
          'PDL' => [
            'name' => 'Pays-de-la-Loire'
          ],
          'PDL' => [
            'name' => 'Provence-Alpes-Côte-d’Azur'
          ],
          'LRE' => [
            'name' => 'La Réunion'
          ],
        ]
      ],
      'GF' => [
        'name' => 'French Guiana',
        'regions' => []
      ],
      'PF' => [
        'name' => 'French Polynesia',
        'regions' => []
      ],
      'TF' => [
        'name' => 'French Southern Territories',
        'regions' => []
      ],
      'GA' => [
        'name' => 'Gabon',
        'regions' => []
      ],
      'GM' => [
        'name' => 'Gambia',
        'regions' => []
      ],
      'GE' => [
        'name' => 'Georgia',
        'regions' => []
      ],
      'DE' => [
        'name' => 'Germany',
        'regions' => []
      ],
      'GH' => [
        'name' => 'Ghana',
        'regions' => []
      ],
      'GI' => [
        'name' => 'Gibraltar',
        'regions' => []
      ],
      'GR' => [
        'name' => 'Greece',
        'regions' => []
      ],
      'GL' => [
        'name' => 'Greenland',
        'regions' => []
      ],
      'GD' => [
        'name' => 'Grenada',
        'regions' => []
      ],
      'GP' => [
        'name' => 'Guadeloupe',
        'regions' => []
      ],
      'GU' => [
        'name' => 'Guam',
        'regions' => []
      ],
      'GT' => [
        'name' => 'Guatemala',
        'regions' => []
      ],
      'GG' => [
        'name' => 'Guernsey',
        'regions' => []
      ],
      'GN' => [
        'name' => 'Guinea',
        'regions' => []
      ],
      'GW' => [
        'name' => 'Guinea-Bissau',
        'regions' => []
      ],
      'GY' => [
        'name' => 'Guyana',
        'regions' => []
      ],
      'HT' => [
        'name' => 'Haiti',
        'regions' => []
      ],
      'HM' => [
        'name' => 'Heard Island & Mcdonald Islands',
        'regions' => []
      ],
      'VA' => [
        'name' => 'Holy See (Vatican City State)',
        'regions' => []
      ],
      'HN' => [
        'name' => 'Honduras',
        'regions' => []
      ],
      'HK' => [
        'name' => 'Hong Kong',
        'regions' => []
      ],
      'HU' => [
        'name' => 'Hungary',
        'regions' => []
      ],
      'IS' => [
        'name' => 'Iceland',
        'regions' => []
      ],
      'IN' => [
        'name' => 'India',
        'regions' => [
          'AP' => [
            'name' => 'Andhra Pradesh'
          ],
          'AR' => [
            'name' => 'Arunachal Pradesh'
          ],
          'AS' => [
            'name' => 'Assam'
          ],
          'BR' => [
            'name' => 'Bihar'
          ],
          'CT' => [
            'name' => 'Chhattisgarh'
          ],
          'GA' => [
            'name' => 'Goa'
          ],
          'GJ' => [
            'name' => 'Gujarat'
          ],
          'HR' => [
            'name' => 'Haryana'
          ],
          'HP' => [
            'name' => 'Himachal Pradesh'
          ],
          'JH' => [
            'name' => 'Jharkhand'
          ],
          'KA' => [
            'name' => 'Karnataka'
          ],
          'KL' => [
            'name' => 'Kerala'
          ],
          'MP' => [
            'name' => 'Madhya Pradesh'
          ],
          'MH' => [
            'name' => 'Maharashtra'
          ],
          'MN' => [
            'name' => 'Manipur'
          ],
          'ML' => [
            'name' => 'Meghalaya'
          ],
          'MZ' => [
            'name' => 'Mizoram'
          ],
          'NL' => [
            'name' => 'Nagaland'
          ],
          'OR' => [
            'name' => 'Odisha'
          ],
          'PB' => [
            'name' => 'Punjab'
          ],
          'RJ' => [
            'name' => 'Rajasthan'
          ],
          'SK' => [
            'name' => 'Sikkim'
          ],
          'TN' => [
            'name' => 'Tamil Nadu'
          ],
          'TG' => [
            'name' => 'Telangana'
          ],
          'TR' => [
            'name' => 'Tripura'
          ],
          'UT' => [
            'name' => 'Uttarakhand'
          ],
          'UP' => [
            'name' => 'Uttar Pradesh'
          ],
          'WP' => [
            'name' => 'West Bengal'
          ],
          'AN' => [
            'name' => 'Andaman and Nicobar Islands'
          ],
          'CH' => [
            'name' => 'Chandigarh'
          ],
          'DN' => [
            'name' => 'Dadra and Nagar Haveli'
          ],
          'DD' => [
            'name' => 'Daman and Diu'
          ],
          'DL' => [
            'name' => 'Delhi'
          ],
          'JK' => [
            'name' => 'Jammu and Kashmir'
          ],
          'LA' => [
            'name' => 'Ladakh'
          ],
          'LD' => [
            'name' => 'Lakshadweep'
          ],
          'PY' => [
            'name' => 'Puducherry'
          ]
        ]
      ],
      'ID' => [
        'name' => 'Indonesia',
        'regions' => []
      ],
      'IR' => [
        'name' => 'Iran, Islamic Republic Of',
        'regions' => []
      ],
      'IQ' => [
        'name' => 'Iraq',
        'regions' => []
      ],
      'IE' => [
        'name' => 'Ireland',
        'regions' => []
      ],
      'IM' => [
        'name' => 'Isle Of Man',
        'regions' => []
      ],
      'IL' => [
        'name' => 'Israel',
        'regions' => []
      ],
      'IT' => [
        'name' => 'Italy',
        'regions' => [
          '65' => [
            'name' => 'Abruzzo'
          ],
          '77' => [
            'name' => 'Basilicata'
          ],
          '78' => [
            'name' => 'Calabria'
          ],
          '72' => [
            'name' => 'Campania'
          ],
          '45' => [
            'name' => 'Emilia-Romagna'
          ],
          '62' => [
            'name' => 'Lazio'
          ],
          '42' => [
            'name' => 'Liguria'
          ],
          '25' => [
            'name' => 'Lombardy'
          ],
          '57' => [
            'name' => 'Marche'
          ],
          '67' => [
            'name' => 'Molise'
          ],
          '21' => [
            'name' => 'Piedmont'
          ],
          '75' => [
            'name' => 'Apulia'
          ],
          '52' => [
            'name' => 'Tuscany'
          ],
          '55' => [
            'name' => 'Umbria'
          ],
          '34' => [
            'name' => 'Veneto'
          ]
        ]
      ],
      'JM' => [
        'name' => 'Jamaica',
        'regions' => []
      ],
      'JP' => [
        'name' => 'Japan',
        'regions' => []
      ],
      'JE' => [
        'name' => 'Jersey',
        'regions' => []
      ],
      'JO' => [
        'name' => 'Jordan',
        'regions' => []
      ],
      'KZ' => [
        'name' => 'Kazakhstan',
        'regions' => []
      ],
      'KE' => [
        'name' => 'Kenya',
        'regions' => []
      ],
      'KI' => [
        'name' => 'Kiribati',
        'regions' => []
      ],
      'KR' => [
        'name' => 'Korea',
        'regions' => []
      ],
      'KW' => [
        'name' => 'Kuwait',
        'regions' => []
      ],
      'KG' => [
        'name' => 'Kyrgyzstan',
        'regions' => []
      ],
      'LA' => [
        'name' => 'Lao People\'s Democratic Republic',
        'regions' => []
      ],
      'LV' => [
        'name' => 'Latvia',
        'regions' => []
      ],
      'LB' => [
        'name' => 'Lebanon',
        'regions' => []
      ],
      'LS' => [
        'name' => 'Lesotho',
        'regions' => []
      ],
      'LR' => [
        'name' => 'Liberia',
        'regions' => []
      ],
      'LY' => [
        'name' => 'Libyan Arab Jamahiriya',
        'regions' => []
      ],
      'LI' => [
        'name' => 'Liechtenstein',
        'regions' => []
      ],
      'LT' => [
        'name' => 'Lithuania',
        'regions' => []
      ],
      'LU' => [
        'name' => 'Luxembourg',
        'regions' => []
      ],
      'MO' => [
        'name' => 'Macao',
        'regions' => []
      ],
      'MK' => [
        'name' => 'Macedonia',
        'regions' => []
      ],
      'MG' => [
        'name' => 'Madagascar',
        'regions' => []
      ],
      'MW' => [
        'name' => 'Malawi',
        'regions' => []
      ],
      'MY' => [
        'name' => 'Malaysia',
        'regions' => [
          '14' => [
            'name' => 'Wilayah Persekutuan Kuala Lumpur'
          ],
          '15' => [
            'name' => 'Wilayah Persekutuan Labuan'
          ],
          '16' => [
            'name' => 'Wilayah Persekutuan Putrajaya'
          ],
          '01' => [
            'name' => 'Johor'
          ],
          '02' => [
            'name' => 'Kedah'
          ],
          '03' => [
            'name' => 'Kelantan'
          ],
          '04' => [
            'name' => 'Melaka'
          ],
          '05' => [
            'name' => 'Negeri Sembilan'
          ],
          '06' => [
            'name' => 'Pahang'
          ],
          '08' => [
            'name' => 'Perak'
          ],
          '09' => [
            'name' => 'Perlis'
          ],
          '07' => [
            'name' => 'Pulau Pinang'
          ],
          '12' => [
            'name' => 'Sabah'
          ],
          '13' => [
            'name' => 'Sarawak'
          ],
          '10' => [
            'name' => 'Selangor'
          ],
          '11' => [
            'name' => 'Terengganu'
          ]
        ]
      ],
      'MV' => [
        'name' => 'Maldives',
        'regions' => []
      ],
      'ML' => [
        'name' => 'Mali',
        'regions' => []
      ],
      'MT' => [
        'name' => 'Malta',
        'regions' => []
      ],
      'MH' => [
        'name' => 'Marshall Islands',
        'regions' => []
      ],
      'MQ' => [
        'name' => 'Martinique',
        'regions' => []
      ],
      'MR' => [
        'name' => 'Mauritania',
        'regions' => []
      ],
      'MU' => [
        'name' => 'Mauritius',
        'regions' => []
      ],
      'YT' => [
        'name' => 'Mayotte',
        'regions' => []
      ],
      'MX' => [
        'name' => 'Mexico',
        'regions' => []
      ],
      'FM' => [
        'name' => 'Micronesia, Federated States Of',
        'regions' => []
      ],
      'MD' => [
        'name' => 'Moldova',
        'regions' => []
      ],
      'MC' => [
        'name' => 'Monaco',
        'regions' => []
      ],
      'MN' => [
        'name' => 'Mongolia',
        'regions' => []
      ],
      'ME' => [
        'name' => 'Montenegro',
        'regions' => []
      ],
      'MS' => [
        'name' => 'Montserrat',
        'regions' => []
      ],
      'MA' => [
        'name' => 'Morocco',
        'regions' => []
      ],
      'MZ' => [
        'name' => 'Mozambique',
        'regions' => []
      ],
      'MM' => [
        'name' => 'Myanmar',
        'regions' => []
      ],
      'NA' => [
        'name' => 'Namibia',
        'regions' => []
      ],
      'NR' => [
        'name' => 'Nauru',
        'regions' => []
      ],
      'NP' => [
        'name' => 'Nepal',
        'regions' => []
      ],
      'NL' => [
        'name' => 'Netherlands',
        'regions' => [
          'NH' => [
            'name' => 'North Holland'
          ],
          'ZH' => [
            'name' => 'South Holland'
          ],
          'ZE' => [
            'name' => 'Zeeland'
          ],
          'UT' => [
            'name' => 'Utrecht'
          ],
          'OV' => [
            'name' => 'Overijssel'
          ],
          'NB' => [
            'name' => 'North Brabant'
          ],
          'LI' => [
            'name' => 'Limburg'
          ],
          'GR' => [
            'name' => 'Groningen'
          ],
          'GE' => [
            'name' => 'Gelderland'
          ],
          'FR' => [
            'name' => 'Friesland'
          ],
          'FL' => [
            'name' => 'Flevoland'
          ],
          'DR' => [
            'name' => 'Drenthe'
          ]
        ]
      ],
      'AN' => [
        'name' => 'Netherlands Antilles',
        'regions' => []
      ],
      'NC' => [
        'name' => 'New Caledonia',
        'regions' => []
      ],
      'NZ' => [
        'name' => 'New Zealand',
        'regions' => []
      ],
      'NI' => [
        'name' => 'Nicaragua',
        'regions' => []
      ],
      'NE' => [
        'name' => 'Niger',
        'regions' => []
      ],
      'NG' => [
        'name' => 'Nigeria',
        'regions' => []
      ],
      'NU' => [
        'name' => 'Niue',
        'regions' => []
      ],
      'NF' => [
        'name' => 'Norfolk Island',
        'regions' => []
      ],
      'MP' => [
        'name' => 'Northern Mariana Islands',
        'regions' => []
      ],
      'NO' => [
        'name' => 'Norway',
        'regions' => []
      ],
      'OM' => [
        'name' => 'Oman',
        'regions' => []
      ],
      'PK' => [
        'name' => 'Pakistan',
        'regions' => []
      ],
      'PW' => [
        'name' => 'Palau',
        'regions' => []
      ],
      'PS' => [
        'name' => 'Palestinian Territory, Occupied',
        'regions' => []
      ],
      'PA' => [
        'name' => 'Panama',
        'regions' => []
      ],
      'PG' => [
        'name' => 'Papua New Guinea',
        'regions' => []
      ],
      'PY' => [
        'name' => 'Paraguay',
        'regions' => []
      ],
      'PE' => [
        'name' => 'Peru',
        'regions' => []
      ],
      'PH' => [
        'name' => 'Philippines',
        'regions' => []
      ],
      'PN' => [
        'name' => 'Pitcairn',
        'regions' => []
      ],
      'PL' => [
        'name' => 'Poland',
        'regions' => [
          '02' => [
            'name' => 'Lower Silesia'
          ],
          '04' => [
            'name' => 'Kuyavia-Pomerania'
          ],
          '06' => [
            'name' => 'Lublin'
          ],
          '08' => [
            'name' => 'Lubusz'
          ],
          '10' => [
            'name' => 'Łódź'
          ],
          '12' => [
            'name' => 'Lesser Poland'
          ],
          '14' => [
            'name' => 'Mazovia'
          ],
          'MZ' => [
            'name' => 'Mazovia'
          ],
          '16' => [
            'name' => 'Opole (Upper Silesia)'
          ],
          '18' => [
            'name' => 'Subcarpathia'
          ],
          '20' => [
            'name' => 'Podlaskie'
          ],
          '22' => [
            'name' => 'Pomerania'
          ],
          '24' => [
            'name' => 'Silesia'
          ],
          '26' => [
            'name' => 'Holy Cross'
          ],
          '28' => [
            'name' => 'Warmia-Masuria'
          ],
          '30' => [
            'name' => 'Greater Poland'
          ],
          '32' => [
            'name' => 'West Pomerania'
          ],
        ]
      ],
      'PT' => [
        'name' => 'Portugal',
        'regions' => []
      ],
      'PR' => [
        'name' => 'Puerto Rico',
        'regions' => []
      ],
      'QA' => [
        'name' => 'Qatar',
        'regions' => []
      ],
      'RE' => [
        'name' => 'Reunion',
        'regions' => []
      ],
      'RO' => [
        'name' => 'Romania',
        'regions' => []
      ],
      'RU' => [
        'name' => 'Russian Federation',
        'regions' => [
          'AD' => [
            'name' => 'Adygeya, Respublika'
          ],
          'AL' => [
            'name' => 'Altay, Respublika'
          ],
          'BA' => [
            'name' => 'Bashkortostan, Respublika'
          ],
          'BU' => [
            'name' => 'Buryatiya, Respublika'
          ],
          'CE' => [
            'name' => 'Chechenskaya Respublika'
          ],
          'CU' => [
            'name' => 'Chuvashskaya Respublika'
          ],
          'DA' => [
            'name' => 'Dagestan, Respublika'
          ],
          'IN' => [
            'name' => 'Ingushetiya, Respublika'
          ],
          'KB' => [
            'name' => 'Kabardino-Balkarskaya Respublika'
          ],
          'KL' => [
            'name' => 'Kalmykiya, Respublika'
          ],
          'KC' => [
            'name' => 'Karachayevo-Cherkesskaya Respublika'
          ],
          'KR' => [
            'name' => 'Kareliya, Respublika'
          ],
          'KK' => [
            'name' => 'Khakasiya, Respublika'
          ],
          'KO' => [
            'name' => 'Komi, Respublika'
          ],
          'ME' => [
            'name' => 'Mariy El, Respublika'
          ],
          'MO' => [
            'name' => 'Mordoviya, Respublika'
          ],
          'SA' => [
            'name' => 'Saha, Respublika'
          ],
          'SE' => [
            'name' => 'Severnaya Osetiya, Respublika'
          ],
          'TA' => [
            'name' => 'Tatarstan, Respublika'
          ],
          'TY' => [
            'name' => 'Tyva, Respublika'
          ],
          'UD' => [
            'name' => 'Udmurtskaya Respublika'
          ],
          'ALT' => [
            'name' => 'Altayskiy kray'
          ],
          'KAM' => [
            'name' => 'Kamchatskiy kray'
          ],
          'KHA' => [
            'name' => 'Khabarovskiy kray'
          ],
          'KDA' => [
            'name' => 'Krasnodarskiy kray'
          ],
          'KYA' => [
            'name' => 'Krasnoyarskiy kray'
          ],
          'PER' => [
            'name' => 'Permskiy kray'
          ],
          'PRI' => [
            'name' => 'Primorskiy kray'
          ],
          'STA' => [
            'name' => 'Stavropol\'skiy kray'
          ],
          'ZAB' => [
            'name' => 'Zabaykal\'skiy kray'
          ],
          'AMU' => [
            'name' => 'Amurskaya oblast\''
          ],
          'ARK' => [
            'name' => 'Arkhangel\'skaya oblast\''
          ],
          'AST' => [
            'name' => 'Astrakhanskaya oblast\''
          ],
          'BEL' => [
            'name' => 'Belgorodskaya oblast\''
          ],
          'BRY' => [
            'name' => 'Bryanskaya oblast\''
          ],
          'CHE' => [
            'name' => 'Chelyabinskaya oblast\''
          ],
          'IRK' => [
            'name' => 'Irkutskaya oblast\''
          ],
          'IVA' => [
            'name' => 'Ivanovskaya oblast\''
          ],
          'KGD' => [
            'name' => 'Kaliningradskaya oblast\''
          ],
          'KLU' => [
            'name' => 'Kaluzhskaya oblast\''
          ],
          'KEM' => [
            'name' => 'Kemerovskaya oblast\''
          ],
          'KIR' => [
            'name' => 'Kirovskaya oblast\''
          ],
          'KOS' => [
            'name' => 'Kostromskaya oblast\''
          ],
          'KGN' => [
            'name' => 'Kurganskaya oblast\''
          ],
          'KRS' => [
            'name' => 'Kurskaya oblast\''
          ],
          'LEN' => [
            'name' => 'Leningradskaya oblast\''
          ],
          'LIP' => [
            'name' => 'Lipetskaya oblast\''
          ],
          'MAG' => [
            'name' => 'Magadanskaya oblast\''
          ],
          'MOS' => [
            'name' => 'Moskovskaya oblast\''
          ],
          'MUR' => [
            'name' => 'Murmanskaya oblast\''
          ],
          'NIZ' => [
            'name' => 'Nizhegorodskaya oblast\''
          ],
          'NGR' => [
            'name' => 'Novgorodskaya oblast\''
          ],
          'NVS' => [
            'name' => 'Novosibirskaya oblast\''
          ],
          'OMS' => [
            'name' => 'Omskaya oblast\''
          ],
          'ORE' => [
            'name' => 'Orenburgskaya oblast\''
          ],
          'ORL' => [
            'name' => 'Orlovskaya oblast\''
          ],
          'PNZ' => [
            'name' => 'Penzenskaya oblast\''
          ],
          'PSK' => [
            'name' => 'Pskovskaya oblast\''
          ],
          'ROS' => [
            'name' => 'Rostovskaya oblast\''
          ],
          'RYA' => [
            'name' => 'Ryazanskaya oblast\''
          ],
          'SAK' => [
            'name' => 'Sakhalinskaya oblast\''
          ],
          'SAM' => [
            'name' => 'Samarskaya oblast\''
          ],
          'SAR' => [
            'name' => 'Saratovskaya oblast\''
          ],
          'SMO' => [
            'name' => 'Smolenskaya oblast\''
          ],
          'SVE' => [
            'name' => 'Sverdlovskaya oblast\''
          ],
          'TAM' => [
            'name' => 'Tambovskaya oblast\''
          ],
          'TOM' => [
            'name' => 'Tomskaya oblast\''
          ],
          'TUL' => [
            'name' => 'Tul\'skaya oblast\''
          ],
          'TVE' => [
            'name' => 'Tverskaya oblast\''
          ],
          'TYU' => [
            'name' => 'Tyumenskaya oblast\''
          ],
          'ULY' => [
            'name' => 'Ul\'yanovskaya oblast\''
          ],
          'VLA' => [
            'name' => 'Vladimirskaya oblast\''
          ],
          'VGG' => [
            'name' => 'Volgogradskaya oblast\''
          ],
          'VLG' => [
            'name' => 'Vologodskaya oblast\''
          ],
          'VOR' => [
            'name' => 'Voronezhskaya oblast\''
          ],
          'YAR' => [
            'name' => 'Yaroslavskaya oblast\''
          ],
          'MOW' => [
            'name' => 'Moskva'
          ],
          'SPE' => [
            'name' => 'Sankt-Peterburg'
          ],
          'YEV' => [
            'name' => 'Yevreyskaya avtonomnaya oblast\''
          ],
          'CHU' => [
            'name' => 'Chukotskiy avtonomnyy okrug'
          ],
          'KHM' => [
            'name' => 'Khanty-Mansiyskiy avtonomnyy okrug'
          ],
          'NEN' => [
            'name' => 'Nenetskiy avtonomnyy okrug'
          ],
          'YAN' => [
            'name' => 'Yamalo-Nenetskiy avtonomnyy okrug'
          ],
        ]
      ],
      'RW' => [
        'name' => 'Rwanda',
        'regions' => []
      ],
      'BL' => [
        'name' => 'Saint Barthelemy',
        'regions' => []
      ],
      'SH' => [
        'name' => 'Saint Helena',
        'regions' => []
      ],
      'KN' => [
        'name' => 'Saint Kitts And Nevis',
        'regions' => []
      ],
      'LC' => [
        'name' => 'Saint Lucia',
        'regions' => []
      ],
      'MF' => [
        'name' => 'Saint Martin',
        'regions' => []
      ],
      'PM' => [
        'name' => 'Saint Pierre And Miquelon',
        'regions' => []
      ],
      'VC' => [
        'name' => 'Saint Vincent And Grenadines',
        'regions' => []
      ],
      'WS' => [
        'name' => 'Samoa',
        'regions' => []
      ],
      'SM' => [
        'name' => 'San Marino',
        'regions' => []
      ],
      'ST' => [
        'name' => 'Sao Tome And Principe',
        'regions' => []
      ],
      'SA' => [
        'name' => 'Saudi Arabia',
        'regions' => []
      ],
      'SN' => [
        'name' => 'Senegal',
        'regions' => []
      ],
      'RS' => [
        'name' => 'Serbia',
        'regions' => []
      ],
      'SC' => [
        'name' => 'Seychelles',
        'regions' => []
      ],
      'SL' => [
        'name' => 'Sierra Leone',
        'regions' => []
      ],
      'SG' => [
        'name' => 'Singapore',
        'regions' => [
          '01' => [
            'name' => 'Central Singapore'
          ],
          '02' => [
            'name' => 'North East'
          ],
          '03' => [
            'name' => 'North West'
          ],
          '04' => [
            'name' => 'South East'
          ],
          '05' => [
            'name' => 'South West'
          ]
        ]
      ],
      'SK' => [
        'name' => 'Slovakia',
        'regions' => []
      ],
      'SI' => [
        'name' => 'Slovenia',
        'regions' => []
      ],
      'SB' => [
        'name' => 'Solomon Islands',
        'regions' => []
      ],
      'SO' => [
        'name' => 'Somalia',
        'regions' => []
      ],
      'ZA' => [
        'name' => 'South Africa',
        'regions' => []
      ],
      'GS' => [
        'name' => 'South Georgia And Sandwich Isl.',
        'regions' => []
      ],
      'ES' => [
        'name' => 'Spain',
        'regions' => []
      ],
      'LK' => [
        'name' => 'Sri Lanka',
        'regions' => []
      ],
      'SD' => [
        'name' => 'Sudan',
        'regions' => []
      ],
      'SR' => [
        'name' => 'Suriname',
        'regions' => []
      ],
      'SJ' => [
        'name' => 'Svalbard And Jan Mayen',
        'regions' => []
      ],
      'SZ' => [
        'name' => 'Swaziland',
        'regions' => []
      ],
      'SE' => [
        'name' => 'Sweden',
        'regions' => []
      ],
      'CH' => [
        'name' => 'Switzerland',
        'regions' => []
      ],
      'SY' => [
        'name' => 'Syrian Arab Republic',
        'regions' => []
      ],
      'TW' => [
        'name' => 'Taiwan',
        'regions' => []
      ],
      'TJ' => [
        'name' => 'Tajikistan',
        'regions' => []
      ],
      'TZ' => [
        'name' => 'Tanzania',
        'regions' => []
      ],
      'TH' => [
        'name' => 'Thailand',
        'regions' => []
      ],
      'TL' => [
        'name' => 'Timor-Leste',
        'regions' => []
      ],
      'TG' => [
        'name' => 'Togo',
        'regions' => []
      ],
      'TK' => [
        'name' => 'Tokelau',
        'regions' => []
      ],
      'TO' => [
        'name' => 'Tonga',
        'regions' => []
      ],
      'TT' => [
        'name' => 'Trinidad And Tobago',
        'regions' => []
      ],
      'TN' => [
        'name' => 'Tunisia',
        'regions' => []
      ],
      'TR' => [
        'name' => 'Turkey',
        'regions' => [
          '01' => [
            'name' => 'Adana'
          ],
          '02' => [
            'name' => 'Adıyaman'
          ],
          '03' => [
            'name' => 'Afyonkarahisar'
          ],
          '04' => [
            'name' => 'Ağrı'
          ],
          '68' => [
            'name' => 'Aksaray'
          ],
          '05' => [
            'name' => 'Amasya'
          ],
          '06' => [
            'name' => 'Ankara'
          ],
          '07' => [
            'name' => 'Antalya'
          ],
          '75' => [
            'name' => 'Ardahan'
          ],
          '08' => [
            'name' => 'Artvin'
          ],
          '09' => [
            'name' => 'Aydın'
          ],
          '10' => [
            'name' => 'Balıkesir'
          ],
          '74' => [
            'name' => 'Bartın'
          ],
          '72' => [
            'name' => 'Batman'
          ],
          '69' => [
            'name' => 'Bayburt'
          ],
          '11' => [
            'name' => 'Bilecik'
          ],
          '12' => [
            'name' => 'Bingöl'
          ],
          '13' => [
            'name' => 'Bitlis'
          ],
          '14' => [
            'name' => 'Bolu'
          ],
          '15' => [
            'name' => 'Burdur'
          ],
          '16' => [
            'name' => 'Bursa'
          ],
          '17' => [
            'name' => 'Çanakkale'
          ],
          '18' => [
            'name' => 'Çankırı'
          ],
          '19' => [
            'name' => 'Çorum'
          ],
          '20' => [
            'name' => 'Denizli'
          ],
          '21' => [
            'name' => 'Diyarbakır'
          ],
          '81' => [
            'name' => 'Düzce'
          ],
          '22' => [
            'name' => 'Edirne'
          ],
          '23' => [
            'name' => 'Elazığ'
          ],
          '24' => [
            'name' => 'Erzincan'
          ],
          '25' => [
            'name' => 'Erzurum'
          ],
          '26' => [
            'name' => 'Eskişehir'
          ],
          '27' => [
            'name' => 'Gaziantep'
          ],
          '28' => [
            'name' => 'Giresun'
          ],
          '29' => [
            'name' => 'Gümüşhane'
          ],
          '30' => [
            'name' => 'Hakkâri'
          ],
          '31' => [
            'name' => 'Hatay'
          ],
          '76' => [
            'name' => 'Iğdır'
          ],
          '32' => [
            'name' => 'Isparta'
          ],
          '34' => [
            'name' => 'İstanbul'
          ],
          '35' => [
            'name' => 'İzmir'
          ],
          '46' => [
            'name' => 'Kahramanmaraş'
          ],
          '78' => [
            'name' => 'Karabük'
          ],
          '70' => [
            'name' => 'Karaman'
          ],
          '36' => [
            'name' => 'Kars'
          ],
          '37' => [
            'name' => 'Kastamonu'
          ],
          '38' => [
            'name' => 'Kayseri'
          ],
          '71' => [
            'name' => 'Kırıkkale'
          ],
          '39' => [
            'name' => 'Kırklareli'
          ],
          '40' => [
            'name' => 'Kırşehir'
          ],
          '79' => [
            'name' => 'Kilis'
          ],
          '41' => [
            'name' => 'Kocaeli'
          ],
          '42' => [
            'name' => 'Konya'
          ],
          '43' => [
            'name' => 'Kütahya'
          ],
          '44' => [
            'name' => 'Malatya'
          ],
          '45' => [
            'name' => 'Manisa'
          ],
          '47' => [
            'name' => 'Mardin'
          ],
          '33' => [
            'name' => 'Mersin'
          ],
          '48' => [
            'name' => 'Muğla'
          ],
          '49' => [
            'name' => 'Muş'
          ],
          '50' => [
            'name' => 'Nevşehir'
          ],
          '51' => [
            'name' => 'Niğde'
          ],
          '52' => [
            'name' => 'Ordu'
          ],
          '80' => [
            'name' => 'Osmaniye'
          ],
          '53' => [
            'name' => 'Rize'
          ],
          '54' => [
            'name' => 'Sakarya'
          ],
          '55' => [
            'name' => 'Samsun'
          ],
          '56' => [
            'name' => 'Siirt'
          ],
          '57' => [
            'name' => 'Sinop'
          ],
          '58' => [
            'name' => 'Sivas'
          ],
          '63' => [
            'name' => 'Şanlıurfa'
          ],
          '73' => [
            'name' => 'Şırnak'
          ],
          '59' => [
            'name' => 'Tekirdağ'
          ],
          '60' => [
            'name' => 'Tokat'
          ],
          '61' => [
            'name' => 'Trabzon'
          ],
          '62' => [
            'name' => 'Tunceli'
          ],
          '64' => [
            'name' => 'Uşak'
          ],
          '65' => [
            'name' => 'Van'
          ],
          '77' => [
            'name' => 'Yalova'
          ],
          '66' => [
            'name' => 'Yozgat'
          ],
          '67' => [
            'name' => 'Zonguldak'
          ]
        ]
      ],
      'TM' => [
        'name' => 'Turkmenistan',
        'regions' => []
      ],
      'TC' => [
        'name' => 'Turks And Caicos Islands',
        'regions' => []
      ],
      'TV' => [
        'name' => 'Tuvalu',
        'regions' => []
      ],
      'UG' => [
        'name' => 'Uganda',
        'regions' => []
      ],
      'UA' => [
        'name' => 'Ukraine',
        'regions' => [
          '40' => [
            'name' => 'Sevastopol'
          ],
          '30' => [
            'name' => 'Kyiv'
          ],
          '43' => [
            'name' => 'Avtonomna Respublika Krym'
          ],
          '18' => [
            'name' => 'Zhytomyrska oblast'
          ],
          '23' => [
            'name' => 'Zaporizka oblast'
          ],
          '21' => [
            'name' => 'Zakarpatska oblast'
          ],
          '07' => [
            'name' => 'Volynska oblast'
          ],
          '05' => [
            'name' => 'Vinnytska oblast'
          ],
          '61' => [
            'name' => 'Ternopilska oblast'
          ],
          '59' => [
            'name' => 'Sumska oblast'
          ],
          '56' => [
            'name' => 'Rivnenska oblast'
          ],
          '53' => [
            'name' => 'Poltavska oblast'
          ],
          '51' => [
            'name' => 'Odeska oblast'
          ],
          '48' => [
            'name' => 'Mykolaivska oblast'
          ],
          '46' => [
            'name' => 'Lvivska oblast'
          ],
          '09' => [
            'name' => 'Luhanska oblast'
          ],
          '32' => [
            'name' => 'Kyivska oblast'
          ],
          '35' => [
            'name' => 'Kirovohradska oblast'
          ],
          '68' => [
            'name' => 'Khmelnytska oblast'
          ],
          '65' => [
            'name' => 'Khersonska oblast'
          ],
          '63' => [
            'name' => 'Kharkivska oblast'
          ],
          '26' => [
            'name' => 'Ivano-Frankivska oblast'
          ],
          '14' => [
            'name' => 'Donetska oblast'
          ],
          '12' => [
            'name' => 'Dnipropetrovska oblast'
          ],
          '77' => [
            'name' => 'Chernivetska oblast'
          ],
          '74' => [
            'name' => 'Chernihivska oblast'
          ],
          '71' => [
            'name' => 'Cherkaska oblast'
          ]
        ]
      ],
      'AE' => [
        'name' => 'United Arab Emirates',
        'regions' => []
      ],
      'GB' => [
        'name' => 'United Kingdom',
        'regions' => []
      ],
      'UM' => [
        'name' => 'United States Outlying Islands',
        'regions' => []
      ],
      'UY' => [
        'name' => 'Uruguay',
        'regions' => []
      ],
      'UZ' => [
        'name' => 'Uzbekistan',
        'regions' => []
      ],
      'VU' => [
        'name' => 'Vanuatu',
        'regions' => []
      ],
      'VE' => [
        'name' => 'Venezuela',
        'regions' => []
      ],
      'VN' => [
        'name' => 'Viet Nam',
        'regions' => []
      ],
      'VG' => [
        'name' => 'Virgin Islands, British',
        'regions' => []
      ],
      'VI' => [
        'name' => 'Virgin Islands, U.S.',
        'regions' => []
      ],
      'WF' => [
        'name' => 'Wallis And Futuna',
        'regions' => []
      ],
      'EH' => [
        'name' => 'Western Sahara',
        'regions' => []
      ],
      'YE' => [
        'name' => 'Yemen',
        'regions' => []
      ],
      'ZM' => [
        'name' => 'Zambia',
        'regions' => []
      ],
      'ZW' => [
        'name' => 'Zimbabwe',
        'regions' => []
      ]
    ];

    if ( ! $region_code ) {
      if ( ! empty( $locations[ $country_code ]['name'] ) ) {
        return $locations[ $country_code ]['name'];
      } elseif( ! $country_code ) {
        return false;
      } else {
        return $country_code;
      }
    } else {
      if ( ! empty( $locations[ $country_code ]['regions'][ $region_code ]['name'] ) ) {
        return $locations[ $country_code ]['regions'][ $region_code ]['name'];
      } elseif( ! $region_code ) {
        return false;
      } else {
        return $region_code;
      }
    }
  }
}

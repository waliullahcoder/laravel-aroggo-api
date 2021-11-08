<?php

namespace App\Search;
use Elasticsearch\ClientBuilder;
use Illuminate\Http\Response;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\Compound\FunctionScoreQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MultiMatchQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\FuzzyQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\PrefixQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\IdsQuery;
use ONGR\ElasticsearchDSL\Search;
use App\Models\Medicine as MedicineModel;

use ONGR\ElasticsearchDSL\SearchEndpoint\SortEndpoint;
use ONGR\ElasticsearchDSL\Sort\FieldSort;

class Medicine
{
    private static $instance;

    private $client;
    private $index = 'arogga';
    private $params;

    public static function init() {
        if(!self::$instance instanceof self) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    function __construct(){
        $this->client = ClientBuilder::create()->build();
        if( ! env('MAIN') ){
            $this->index = $this->index . '_staging';
        }
        $this->params = [
            'index' => $this->index, //like database
            //'type' => 'medicine', //like DB table
        ];
    }

    public function indicesDelete(){
        $this->client->indices()->delete( [ 'index' => $this->index ] );
        return response()->json([
            'status' => "success",
            'message' => 'Done.'
        ], Response::HTTP_BAD_REQUEST);
    }

    private function getFields(){
        return [
            'm_name' => [
                'type' => 'text',
                'index_prefixes' => [
                    'min_chars' => 2,
                    'max_chars' => 5,
                ],
                'copy_to' => 'shamim_search_field',
            ],
            'm_form' => [
                'type' => 'text',
                'index' => false,
                'copy_to' => 'shamim_search_field',
            ],
            'm_strength' => [
                'type' => 'text',
                'index' => false,
                'copy_to' => 'shamim_search_field',
            ],
            'm_unit' => [
                'type' => 'text',
                'index' => false,
                'copy_to' => 'shamim_search_field',
            ],
            'm_generic' => [
                'type' => 'text',
                'index' => false,
                'copy_to' => 'shamim_search_field',
            ],
            'm_company' => [
                'type' => 'text',
                'index' => false,
                'copy_to' => 'shamim_search_field',
            ],
            'shamim_search_field' => [
                'type' => 'search_as_you_type',
            ],
            //https://www.elastic.co/guide/en/elasticsearch/reference/master/keyword.html#keyword-field-type
            'm_g_id' => [
                'type' => 'keyword',
            ],
            'm_c_id' => [
                'type' => 'keyword',
            ],
            'm_cat_id' => [
                'type' => 'keyword',
            ],
            'm_status' => [
                'type' => 'keyword',
            ],
            'm_category' => [
                'type' => 'keyword',
            ],
            'm_rob' => [
                'type' => 'boolean',
            ],
            'm_min' => [
                'type' => 'integer',
            ],
            'm_max' => [
                'type' => 'integer',
            ],
            'm_price' => [
                'type' => 'scaled_float',
                'scaling_factor' => 100,
            ],
            'm_d_price' => [
                'type' => 'scaled_float',
                'scaling_factor' => 100,
            ],
            'medicineCountViewed' => [
                'type' => 'integer',
            ],
            'medicineCountPurchased' => [
                'type' => 'integer',
            ],
            'imagesCount' => [
                'type' => 'integer',
            ],
        ];
    }

    public function indicesCreate(){
        //$this->client->indices()->delete( [ 'index' => $this->index ] );

        $params = $this->params;
        $params['body']['mappings'] = [
            'properties' => $this->getFields(),
        ];

        //$this->client->indices()->delete( [ 'index' => 'arogga_test1' ] );
        //$this->client->indices()->delete( [ 'index' => 'arogga_test2' ] );

        // Create the index with mappings now
        $response = $this->client->indices()->create( $params );
        return response()->json([
            'status' => "success",
            'message' => 'Done.'
        ], Response::HTTP_BAD_REQUEST);

        //return $response;
    }

    public function bulkIndex(){

        $params = ['body' => []];

        $medicines = MedicineModel::all();
        $i = 1;

        foreach( $medicines as $medicine ){
            $params['body'][] = [
                'index' => [
                    '_index' => $this->index,
                    '_id'    => $medicine->m_id
                ]
            ];

            $m_array = $medicine->toArray();
            $m_array['medicineCountViewed'] = $medicine->getCount('Viewed');
            $m_array['medicineCountPurchased'] = $medicine->getCount('Purchased');
            $m_array['m_generic'] = $medicine->m_generic;
            $m_array['m_company'] = $medicine->m_company;

            $images = $medicine->getMeta( 'images' );
            if( $images ){
                $m_array['images'] = $images;
                $m_array['imagesCount'] = count( $images );
            }

            $params['body'][] = $m_array;

            // Every 1000 documents stop and send the bulk request
            if ($i % 1000 == 0) {
                $responses = $this->client->bulk($params);

                // erase the old bulk request
                $params = ['body' => []];

                // unset the bulk response when you are done to save memory
                unset($responses);
            }
            $i++;
        }

        // Send the last batch if it exists
        if (!empty($params['body'])) {
            $responses = $this->client->bulk($params);
        }
        return response()->json([
            'status' => "success",
            'message' => 'Done.'
        ], Response::HTTP_BAD_REQUEST);
    }

    /**
     * Undocumented function
     *
     * @author Shamim Hasan <shamim@arogga.com>
     * @since 1.0.0
     *
     * @param [type] $id
     * @param [type] $body
     *
     * @return void
     */
    public function index( $id, $body ){
        $params = $this->params;
        $params['id'] = $id;
        $params['body'] = $body;

        $this->client->index( $params );
        return true;
    }

    public function update( $id, $body ){
        $params = $this->params;
        $params['id'] = $id;
        $params['body']['doc'] = $body;

        $this->client->update( $params );
        return true;
    }

    public function delete( $id ){
        $params = $this->params;
        $params['id'] = $id;

        $this->client->delete( $params );
        return true;
    }

    public function search( $args = [] ) {
        $q = '';
        if( ! empty( $args['search'] ) ){
            $q = mb_strtolower( trim( $args['search'] ) );
        }

        $search = new Search();
        if( ! empty( $args['per_page'] ) ){
            $search->setSize( $args['per_page'] );
        }
        if( ! empty( $args['limit'] ) ){
            $search->setFrom( $args['limit'] );
        }

        if( ! empty( $args['orderBy'] ) ){
            //$search->addSort( new FieldSort( 'm_name' == $args['orderBy'] ? $args['orderBy'] . '.raw' : $args['orderBy'], $args['order'] ) );
        }

        if( ! empty( $args['ids'] ) ){
            //$boolQuery->add( new IdsQuery( $args['ids'] ), BoolQuery::FILTER );
            $search->addQuery( new IdsQuery( $args['ids'] ) );
        }

        $boolQuery = new BoolQuery();

        foreach ( $this->getFields() as $field => $values ) {
            if( in_array( $values['type'], ['keyword', 'boolean'] ) && !empty( $args[ $field ] ) ){
                $boolQuery->add( new TermQuery( $field, $args[ $field ] ), BoolQuery::FILTER );
            }
        }

        if( ! empty( $args['havePic'] ) ){
            $boolQuery->add( new RangeQuery( 'imagesCount', ['gte' => 1] ), BoolQuery::FILTER );
        }

        if( \strlen( $q ) >= 2 ){
            if( false === \strpos( $q, ' ' ) ){
                $boolQuery->add( new PrefixQuery( 'm_name', $q ), BoolQuery::SHOULD );
            }

            /*
            $boolQuery->add(
                new FuzzyQuery(
                    'm_name',
                    $q,
                    [
                        'fuzziness'     => 'AUTO',
                        'prefix_length' => 2,
                    ]
                ),
                BoolQuery::SHOULD
            );
            */
            $boolQuery->add(
                new MultiMatchQuery(
                    [
                        'shamim_search_field',
                        'shamim_search_field._2gram',
                        'shamim_search_field._3gram',
                        //'shamim_search_field._index_prefix',
                    ],
                    $q,
                    [
                        'type'          => 'bool_prefix',
                        'fuzziness'     => 'AUTO',
                        'prefix_length' => 2,
                    ]
                ),
                BoolQuery::MUST
            );
        }

        $functionScoreQuery = new FunctionScoreQuery( $boolQuery, [
            'score_mode' => 'sum',
            'boost_mode' => 'sum',
            'max_boost'  => 10,
        ] );
        $functionScoreQuery->addFieldValueFactorFunction( 'medicineCountViewed', 0.0001, 'log1p', null, 0.0001 );
        $functionScoreQuery->addFieldValueFactorFunction( 'medicineCountPurchased', 0.01, 'log1p', null, 0.0001 );

        $search->addQuery( $functionScoreQuery );
        //$queryArray = $search->toArray();

        $client = ClientBuilder::create()->build();

        $params = $this->params;
        $params['body'] = $search->toArray();

        //Response::instance()->sendData( $params );

        $all_data = [];
        $docs = $client->search($params);
        //return $docs;
        foreach( $docs['hits']['hits'] as $doc ){
            $medicine = getMedicine($doc['_source']);
            $m = $doc['_source'];
            $data = [];

            if( ! empty( $args['isAdmin'] ) ){
                $data = $medicine->toArray();
                $data['id'] = $medicine->m_id;
                $data['m_generic'] = $m['m_generic']??'';
                $data['m_company'] = $m['m_company']??'';
                $data['attachedFiles'] = isset($m['images']) ? getPicUrlsAdmin( $m['images'] ) : [];
            } else {
                $data = [
                    'id'       => $medicine->m_id,
                    'name'     => $medicine->m_name,
                    'strength' => $medicine->m_strength,
                    'form'     => $medicine->m_form,
                    'unit'     => $medicine->m_unit,
                    'rx_req'   => $medicine->m_rx_req,
                    'rob'      => $medicine->m_rob,
                    'comment'  => $medicine->m_comment,
                    'price'    => $medicine->m_price,
                    'd_price'  => $medicine->m_d_price,
                    'generic'  => $m['m_generic']??'',
                    'company'  => $m['m_company']??'',
                    'pic_url'  => isset($m['images']) ? getPicUrl( $m['images'] ) : '',
                ];
            }
            array_push( $all_data, $data );
        }
        $data = [
            'data' => $all_data,
            'total' => $docs['hits']['total']['value'],
        ];

        return $data;
    }

    public function search2( $q, $from = 0, $size = 20, $args = [] ){
        $q = trim( \rawurldecode($q) );

        $params = $this->params;
        $query = [];
        $params['body']['from'] = $from;
        $params['body']['size'] = $size;

        if( !empty($args['orderBy']) && !empty($args['order']) ){
            $params['body']['sort'] = [
                $args['orderBy'] => $args['order'],
            ];
        }
        $query['bool']['filter'][] = [
            'term' => [ 'm_status' => 'active' ],
        ];

        if( \strlen( $q ) >= 2 && false === \strpos( $q, ' ' ) ){
            $query['bool']['should'][]['prefix']['m_name'] = [
                //'shamim_search_field._index_prefix' => $q,
                'value' => $q,
            ];
            $query['bool']['should'][]['fuzzy']['m_name'] = [
                'value' => $q,
                'fuzziness' => 'AUTO',
                'prefix_length' => 2,
            ];
        }

        $query['bool']['should'][]['multi_match'] = [
            'query' => $q,
            'type' => 'bool_prefix',
            'fuzziness' => 'AUTO',
            'prefix_length' => 2,
            'fields' => [
                'shamim_search_field',
                'shamim_search_field._2gram',
                'shamim_search_field._3gram',
                'shamim_search_field._index_prefix',
            ],
        ];
        $params['body']['query']['function_score'] = [
            "query" => $query,
            'functions' => [
                [
                    'field_value_factor' => [
                        'field' => 'm_view_count',
                        'factor' => 0.0001,
                        'missing' => 0.0001,
                        'modifier' => 'log1p',
                    ],
                ],
                [
                    'field_value_factor' => [
                        'field' => 'm_purchased_count',
                        'factor' => 0.01,
                        'missing' => 0.0001,
                        'modifier' => 'log1p',
                    ],
                ],
            ],
            'score_mode' => 'sum',
            'boost_mode' => 'sum',
            'max_boost' => 10,

        ];

        $result = array();
        $i = 0;
        $query = $this->client->search( $params );
        $hits = sizeof($query['hits']['hits']);
        $hit = $query['hits']['hits'];
        $result['searchfound'] = $hits;
        $result['q'] = $q;
        while ($i < $hits) {
            $result['result'][$i] = $query['hits']['hits'][$i]['_source'];
            $i++;
        }
        return response()->json([
            'status' => "success",
            'message' => '',
            'data' => $query
        ], Response::HTTP_BAD_REQUEST);
//        return $result;
    }
}
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DSG_Retriever {

    private const MAX_CONTENT_LENGTH = 800;

    /**
     * @return array<int, array{title: string, content: string, source: string}>
     */
    public static function get_references( int $template_id, string $user_input, int $limit = 3 ): array {
        $ref_enabled = get_post_meta( $template_id, '_ds_ref_enabled', true );
        if ( $ref_enabled !== '1' ) {
            return [];
        }

        $ref_tags     = get_post_meta( $template_id, '_ds_ref_tags', true );
        $ref_keywords = get_post_meta( $template_id, '_ds_ref_keywords', true );
        $ref_limit    = get_post_meta( $template_id, '_ds_ref_limit', true );
        $limit        = ( $ref_limit !== '' && $ref_limit !== false ) ? max( 1, (int) $ref_limit ) : $limit;

        $results = self::query_reference_docs( $ref_tags, $ref_keywords, $user_input, $limit );

        return $results;
    }

    /**
     * @return array<int, array{title: string, content: string, source: string}>
     */
    private static function query_reference_docs( string $tags, string $keywords, string $user_input, int $limit ): array {
        $args = [
            'post_type'      => 'ds_reference_doc',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'relevance',
            'order'          => 'DESC',
        ];

        $search_terms = self::build_search_string( $keywords, $user_input );
        if ( ! empty( $search_terms ) ) {
            $args['s'] = $search_terms;
        }

        $tag_slugs = self::parse_csv( $tags );
        if ( ! empty( $tag_slugs ) ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'ds_ref_tag',
                    'field'    => 'slug',
                    'terms'    => $tag_slugs,
                ],
            ];
            if ( empty( $search_terms ) ) {
                $args['orderby'] = 'date';
            }
        }

        if ( empty( $search_terms ) && empty( $tag_slugs ) ) {
            return [];
        }

        $query   = new WP_Query( $args );
        $results = [];

        foreach ( $query->posts as $post ) {
            $content = wp_strip_all_tags( $post->post_content );
            if ( mb_strlen( $content ) > self::MAX_CONTENT_LENGTH ) {
                $content = mb_substr( $content, 0, self::MAX_CONTENT_LENGTH ) . '…(已截断)';
            }
            $results[] = [
                'title'   => $post->post_title,
                'content' => $content,
                'source'  => 'cpt',
            ];
        }

        wp_reset_postdata();

        return $results;
    }

    private static function build_search_string( string $keywords, string $user_input ): string {
        $parts = [];

        $kw = self::parse_csv( $keywords );
        if ( ! empty( $kw ) ) {
            $parts = array_merge( $parts, $kw );
        }

        $input_words = self::extract_keywords( $user_input );
        if ( ! empty( $input_words ) ) {
            $parts = array_merge( $parts, $input_words );
        }

        return implode( ' ', array_unique( $parts ) );
    }

    /**
     * @return string[]
     */
    private static function extract_keywords( string $text, int $max = 5 ): array {
        $text = wp_strip_all_tags( $text );
        $text = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $text );
        $words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );

        $stop_words = [
            '的', '了', '在', '是', '我', '有', '和', '就', '不', '人', '都',
            '一', '一个', '上', '也', '很', '到', '说', '要', '去', '你',
            '会', '着', '没有', '看', '好', '自己', '这', '他', '她', '它',
            'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been',
            'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will',
            'would', 'could', 'should', 'may', 'might', 'shall', 'can',
            'to', 'of', 'in', 'for', 'on', 'with', 'at', 'by', 'from',
            'as', 'into', 'through', 'during', 'before', 'after',
            'and', 'but', 'or', 'nor', 'not', 'so', 'yet',
            'it', 'its', 'this', 'that', 'these', 'those',
        ];

        $filtered = [];
        foreach ( $words as $w ) {
            $lower = mb_strtolower( $w );
            if ( mb_strlen( $w ) >= 2 && ! in_array( $lower, $stop_words, true ) ) {
                $filtered[] = $w;
            }
        }

        return array_slice( array_unique( $filtered ), 0, $max );
    }

    /**
     * @return string[]
     */
    private static function parse_csv( string $value ): array {
        if ( empty( trim( $value ) ) ) {
            return [];
        }
        return array_filter( array_map( 'trim', preg_split( '/[,，\s]+/u', $value ) ) );
    }

    public static function format_context( array $references ): string {
        if ( empty( $references ) ) {
            return '';
        }

        $output  = "\n\n## 参考材料（请在逻辑与意象衔接方式上进行学习和借鉴）\n\n";

        foreach ( $references as $i => $ref ) {
            $num = $i + 1;
            $output .= "【参考{$num}】{$ref['title']}\n";
            $output .= "{$ref['content']}\n\n";
        }

        $output .= "请特别留意上述参考文本中意象之间的因果、对比或递进关系，并在新的生成内容中保持类似的逻辑连贯性，而非简单堆砌相似意象。\n";

        return $output;
    }
}

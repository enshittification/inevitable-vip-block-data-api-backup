<?php
/**
 * Class GraphQLAPITest
 *
 * @package vip-block-data-api
 */

namespace WPCOMVIP\BlockDataApi;

/**
 * Tests for the GraphQL API.
 */
class GraphQLAPITest extends RegistryTestCase {

	public function test_is_graphql_enabled_true() {
		$this->assertTrue( apply_filters( 'vip_block_data_api__is_graphql_enabled', true ) );
	}

	public function test_is_graphql_enabled_false() {
		$is_graphql_enabled_function = function () {
			return false;
		};
		add_filter( 'vip_block_data_api__is_graphql_enabled', $is_graphql_enabled_function, 10, 0 );
		$this->assertFalse( apply_filters( 'vip_block_data_api__is_graphql_enabled', true ) );
		remove_filter( 'vip_block_data_api__is_graphql_enabled', $is_graphql_enabled_function, 10, 0 );
	}

	public function test_get_blocks_data() {
		$html = '
            <!-- wp:paragraph -->
            <p>Welcome to WordPress. This is your first post. Edit or delete it, then start writing!</p>
            <!-- /wp:paragraph -->
            
            <!-- wp:quote -->
            <blockquote class="wp-block-quote"><!-- wp:paragraph -->
            <p>This is a heading inside a quote</p>
            <!-- /wp:paragraph -->
            
            <!-- wp:quote -->
            <blockquote class="wp-block-quote"><!-- wp:heading -->
            <h2 class="wp-block-heading">This is a heading</h2>
            <!-- /wp:heading --></blockquote>
            <!-- /wp:quote --></blockquote>
            <!-- /wp:quote -->
        ';

		$expected_blocks = [
			'blocks' => [
				[
					'name'       => 'core/paragraph',
					'attributes' => [
						array(
							'name'  => 'content',
							'value' => 'Welcome to WordPress. This is your first post. Edit or delete it, then start writing!',
						),
						array(
							'name'  => 'dropCap',
							'value' => '',
						),
					],
					'id'         => '1',
				],
				[
					'name'        => 'core/quote',
					'attributes'  => [
						array(
							'name'  => 'value',
							'value' => '',
						),
						array(
							'name'  => 'citation',
							'value' => '',
						),
					],
					'innerBlocks' => [
						[
							'parentId'   => '2',
							'name'       => 'core/paragraph',
							'attributes' => [
								array(
									'name'  => 'content',
									'value' => 'This is a heading inside a quote',
								),
								array(
									'name'  => 'dropCap',
									'value' => '',
								),
							],
							'id'         => '3',
						],
						[
							'parentId'   => '2',
							'name'       => 'core/quote',
							'attributes' => [
								array(
									'name'  => 'value',
									'value' => '',
								),
								array(
									'name'  => 'citation',
									'value' => '',
								),
							],
							'id'         => '4',
						],
						[
							'parentId'   => '4',
							'name'       => 'core/heading',
							'attributes' => [
								array(
									'name'  => 'content',
									'value' => 'This is a heading',
								),
								array(
									'name'  => 'level',
									'value' => '2',
								),
							],
							'id'         => '5',
						],
					],
					'id'          => '2',
				],
			],
		];

		$post = $this->factory()->post->create_and_get( [
			'post_content' => $html,
		] );

		$graphQLPost = new \WPGraphQL\Model\Post( $post );

		$blocks_data = GraphQLApi::get_blocks_data( $graphQLPost );

		$this->assertEquals( $expected_blocks, $blocks_data );
	}
}

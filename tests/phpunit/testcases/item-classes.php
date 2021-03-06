<?php
/**
 * @group item_classes
 */
class BuddyDrive_Item_Classes_Tests extends BuddyDrive_TestCase {

	public function setUp() {
		parent::setUp();

		$this->current_user = get_current_user_id();
		$this->user_id      = $this->factory->user->create();
		$this->set_current_user( $this->user_id );
		$this->expected_ids = array();
		$this->create_files();
	}

	public function create_files() {
		$args = array(
			'type'             => buddydrive_get_file_post_type(),
			'user_id'          => $this->user_id,
			'title'            => 'screenshot-1.png',
			'content'          => 'foo file',
			'mime_type'        => 'image/png',
			'guid'             => trailingslashit( buddydrive()->upload_url ) . 'screenshot-1.png',
		);

		$this->expected_ids['foo'] = buddydrive_save_item( $args );

		$args = array_merge( $args, array(
			'title'     => 'readme.txt',
			'content'   => 'bar file',
			'mime_type' => 'text/plain',
			'guid'      => trailingslashit( buddydrive()->upload_url ) . 'readme.txt',
		) );

		$this->expected_ids['bar'] = buddydrive_save_item( $args );
	}

	public function set_displayed_user_id( $user_id = 0 ) {
		return $this->user_id;
	}

	public function tearDown() {
		parent::tearDown();

		$this->set_current_user( $this->current_user );
	}

	/**
	 * @group get
	 */
	public function test_buddydrive_item_get_by_id() {
		$by_id = new BuddyDrive_Item();

		// Get by ID
		$by_id->get( array(
			'id'   => $this->expected_ids['foo'],
			'type' => buddydrive_get_file_post_type(),
		) );

		$this->assertTrue( (int) $by_id->query->found_posts === 1 );

		$file = wp_list_pluck( $by_id->query->posts, 'ID' );
		$this->assertTrue( $this->expected_ids['foo'] === (int) $file[0] );
	}

	/**
	 * @group get
	 */
	public function test_buddydrive_item_get_by_name() {
		$by_name = new BuddyDrive_Item();

		// Get by name
		$by_name->get( array(
			'name'   => 'readme-txt',
			'type'   => buddydrive_get_file_post_type(),
		) );

		$this->assertTrue( (int) $by_name->query->found_posts === 1 );

		$file = wp_list_pluck( $by_name->query->posts, 'ID' );
		$this->assertTrue( $this->expected_ids['bar'] === (int) $file[0] );
	}

	/**
	 * @group get
	 */
	public function test_buddydrive_item_get_by_user_id() {
		$user_id = $this->factory->user->create();
		$file_object = buddydrive_get_buddyfile( $this->expected_ids['foo'] );

		buddydrive_update_item( array(
			'user_id' => $user_id,
		), $file_object );

		$by_user_id = new BuddyDrive_Item();

		// Get by name
		$by_user_id->get( array(
			'user_id'           => $this->user_id,
			'type'              => buddydrive_get_file_post_type(),
			'buddydrive_scope'  => 'admin',
		) );

		$this->assertTrue( (int) $by_user_id->query->found_posts === 1 );

		$file = wp_list_pluck( $by_user_id->query->posts, 'ID' );
		$this->assertTrue( $this->expected_ids['bar'] === (int) $file[0] );
	}

	/**
	 * @group get
	 * @group scope
	 */
	public function test_buddydrive_item_get_by_scope() {
		$u2 = $this->factory->user->create();

		// Admin
		$this->set_current_user( 1 );

		$by_scope = new BuddyDrive_Item();

		// Get by scope
		$by_scope->get( array(
			'type'              => buddydrive_get_file_post_type(),
			'buddydrive_scope'  => 'admin',
		) );

		// Admin should see everything
		$this->assertTrue( (int) $by_scope->query->found_posts === 2 );

		// Update the privacy of the file
		$file_object = buddydrive_get_buddyfile( $this->expected_ids['foo'] );

		buddydrive_update_item( array(
			'privacy' => 'public',
		), $file_object );

		// Any user
		$this->set_current_user( $u2 );

		add_filter( 'bp_displayed_user_id', array( $this, 'set_displayed_user_id' ), 10, 1 );

		$by_scope = new BuddyDrive_Item();

		// Get by scope
		$by_scope->get( array(
			'type'              => buddydrive_get_file_post_type(),
			'buddydrive_scope'  => 'files',
		) );

		$file = wp_list_pluck( $by_scope->query->posts, 'ID' );
		$this->assertTrue( $this->expected_ids['foo'] === (int) $file[0], 'only public files should be listed' );

		// The owner
		$this->set_current_user( $this->user_id );

		$by_scope = new BuddyDrive_Item();

		// Get by scope
		$by_scope->get( array(
			'type'              => buddydrive_get_file_post_type(),
			'buddydrive_scope'  => 'files',
		) );

		// Owner should see everything
		$this->assertTrue( (int) $by_scope->query->found_posts === 2 );

		remove_filter( 'bp_displayed_user_id', array( $this, 'set_displayed_user_id' ), 10, 1 );

		// Any user
		$this->set_current_user( $u2 );

		// Update the privacy and owner of the file
		$file_object = buddydrive_get_buddyfile( $this->expected_ids['bar'] );

		buddydrive_update_item( array(
			'privacy' => 'public',
			'user_id' => $u2,
		), $file_object );

		$by_scope = new BuddyDrive_Item();

		// Get by scope
		$by_scope->get( array(
			'type'              => buddydrive_get_file_post_type(),
			'buddydrive_scope'  => 'public',
		) );

		// Custom loops should be able to list all public files
		$this->assertTrue( (int) $by_scope->query->found_posts === 2 );

		buddydrive_update_item( array(
			'privacy' => 'private',
		), $file_object );

		$by_scope = new BuddyDrive_Item();

		// Get by scope
		$by_scope->get( array(
			'type'              => buddydrive_get_file_post_type(),
			'buddydrive_scope'  => 'public',
		) );

		// Custom loops should be able to list all public files
		$this->assertTrue( (int) $by_scope->query->found_posts === 1 );
	}
}

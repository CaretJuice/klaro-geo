<?php
/**
 * Tests for the Klaro_Geo_Option class
 */

class KlaroGeoOptionClassTest extends WP_UnitTestCase {
    /**
     * Test option name
     */
    private $option_name = 'klaro_geo_test_option';

    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        // Make sure the option doesn't exist
        delete_option($this->option_name);
    }

    /**
     * Tear down after each test
     */
    public function tearDown(): void {
        // Clean up
        delete_option($this->option_name);
        parent::tearDown();
    }

    /**
     * Test constructor and default values
     */
    public function test_constructor() {
        // Test with default value
        $default_value = array('test' => 'value');
        $option = new Klaro_Geo_Option($this->option_name, $default_value);
        
        // Check that the option was loaded with default value
        $this->assertEquals($default_value, $option->get());
        
        // Check that the option is not modified
        $reflection = new ReflectionClass($option);
        $property = $reflection->getProperty('is_modified');
        $property->setAccessible(true);
        $this->assertFalse($property->getValue($option));
    }

    /**
     * Test loading option
     */
    public function test_load() {
        // Set up test data
        $test_data = array('key1' => 'value1', 'key2' => 'value2');
        update_option($this->option_name, $test_data);
        
        // Create option and load
        $option = new Klaro_Geo_Option($this->option_name);
        $loaded_data = $option->load();
        
        // Check that the data was loaded correctly
        $this->assertEquals($test_data, $loaded_data);
        
        // Test loading JSON string
        $json_data = array('json_key' => 'json_value');
        update_option($this->option_name, json_encode($json_data));
        
        $option = new Klaro_Geo_Option($this->option_name);
        $loaded_json = $option->load();
        
        // Check that JSON was decoded correctly
        $this->assertEquals($json_data, $loaded_json);
        
        // Test loading invalid data
        update_option($this->option_name, 'not_json_or_array');
        
        $default_value = array('default' => 'value');
        $option = new Klaro_Geo_Option($this->option_name, $default_value);
        $loaded_invalid = $option->load();
        
        // Check that default value was used
        $this->assertEquals($default_value, $loaded_invalid);
    }

    /**
     * Test saving option
     */
    public function test_save() {
        // Create option with test data
        $test_data = array('save_key' => 'save_value');
        $option = new Klaro_Geo_Option($this->option_name, $test_data);
        
        // Option should not be modified yet
        $reflection = new ReflectionClass($option);
        $property = $reflection->getProperty('is_modified');
        $property->setAccessible(true);
        $this->assertFalse($property->getValue($option));
        
        // Save should return true but not actually save (not modified)
        $this->assertTrue($option->save());
        
        // Option should still not exist in database
        $this->assertFalse(get_option($this->option_name, false));
        
        // Modify the option
        $option->set_key('new_key', 'new_value');
        
        // Option should now be modified
        $reflection = new ReflectionClass($option);
        $property = $reflection->getProperty('is_modified');
        $property->setAccessible(true);
        $this->assertTrue($property->getValue($option));
        
        // Save should actually save now
        $this->assertTrue($option->save());
        
        // Option should now exist in database
        $saved_data = get_option($this->option_name);
        $this->assertEquals(array('save_key' => 'save_value', 'new_key' => 'new_value'), $saved_data);
        
        // Option should no longer be modified
        $reflection = new ReflectionClass($option);
        $property = $reflection->getProperty('is_modified');
        $property->setAccessible(true);
        $this->assertFalse($property->getValue($option));
    }

    /**
     * Test getting and setting keys
     */
    public function test_get_set_key() {
        // Create option with test data
        $test_data = array('key1' => 'value1', 'key2' => 'value2');
        $option = new Klaro_Geo_Option($this->option_name, $test_data);
        
        // Test getting existing key
        $this->assertEquals('value1', $option->get_key('key1'));
        
        // Test getting non-existent key
        $this->assertNull($option->get_key('non_existent'));
        
        // Test getting non-existent key with default
        $this->assertEquals('default', $option->get_key('non_existent', 'default'));
        
        // Test setting existing key
        $option->set_key('key1', 'new_value');
        $this->assertEquals('new_value', $option->get_key('key1'));
        $reflection = new ReflectionClass($option);
        $property = $reflection->getProperty('is_modified');
        $property->setAccessible(true);
        $this->assertTrue($property->getValue($option));
        
        // Test setting new key
        $option->set_key('key3', 'value3');
        $this->assertEquals('value3', $option->get_key('key3'));
        
        // Test has_key
        $this->assertTrue($option->has_key('key1'));
        $this->assertFalse($option->has_key('non_existent'));
        
        // Test remove_key
        $option->remove_key('key1');
        $this->assertFalse($option->has_key('key1'));
        
        // Test removing non-existent key (should not error)
        $option->remove_key('non_existent');
    }

    /**
     * Test getting and setting nested values
     */
    public function test_nested() {
        // Create option with nested data
        $nested_data = array(
            'level1' => array(
                'level2' => array(
                    'level3' => 'value'
                )
            )
        );
        $option = new Klaro_Geo_Option($this->option_name, $nested_data);
        
        // Test getting nested value
        $this->assertEquals('value', $option->get_nested(array('level1', 'level2', 'level3')));
        
        // Test getting non-existent nested value
        $this->assertNull($option->get_nested(array('level1', 'non_existent')));
        
        // Test getting non-existent nested value with default
        $this->assertEquals('default', $option->get_nested(array('level1', 'non_existent'), 'default'));
        
        // Test setting nested value
        $option->set_nested(array('level1', 'level2', 'level3'), 'new_value');
        $this->assertEquals('new_value', $option->get_nested(array('level1', 'level2', 'level3')));
        
        // Test setting new nested value
        $option->set_nested(array('level1', 'level2', 'level4'), 'another_value');
        $this->assertEquals('another_value', $option->get_nested(array('level1', 'level2', 'level4')));
        
        // Test creating deep nested structure
        $option->set_nested(array('new', 'deep', 'path'), 'deep_value');
        $this->assertEquals('deep_value', $option->get_nested(array('new', 'deep', 'path')));
        
        // Test removing nested value
        $option->remove_nested(array('level1', 'level2', 'level3'));
        $this->assertNull($option->get_nested(array('level1', 'level2', 'level3')));
        
        // Test removing non-existent nested value (should not error)
        $option->remove_nested(array('non', 'existent', 'path'));
    }

    /**
     * Test merging values
     */
    public function test_merge() {
        // Create option with test data
        $test_data = array(
            'key1' => 'value1',
            'nested' => array(
                'sub1' => 'subvalue1'
            )
        );
        $option = new Klaro_Geo_Option($this->option_name, $test_data);
        
        // Test non-recursive merge
        $merge_data = array(
            'key2' => 'value2',
            'nested' => array(
                'sub2' => 'subvalue2'
            )
        );
        $option->merge($merge_data, false);
        
        // Non-recursive merge should overwrite nested array
        $this->assertEquals(array('sub2' => 'subvalue2'), $option->get_key('nested'));
        
        // Reset option
        $option = new Klaro_Geo_Option($this->option_name, $test_data);
        
        // Test recursive merge
        $option->merge($merge_data, true);
        
        // Recursive merge should merge nested arrays
        $expected_nested = array(
            'sub1' => 'subvalue1',
            'sub2' => 'subvalue2'
        );
        $this->assertEquals($expected_nested, $option->get_key('nested'));
        
        // Test merging invalid data (should not error)
        $option->merge('not_an_array');
        
        // Value should remain unchanged
        $this->assertEquals($expected_nested, $option->get_key('nested'));
    }

    /**
     * Test resetting to default value
     */
    public function test_reset() {
        // Create option with test data
        $default_value = array('default' => 'value');
        $option = new Klaro_Geo_Option($this->option_name, $default_value);
        
        // Modify the option
        $option->set_key('new_key', 'new_value');
        $this->assertTrue($option->has_key('new_key'));
        
        // Reset the option
        $option->reset();

        // Option should be back to default value
        $this->assertEquals($default_value, $option->get());
        $this->assertFalse($option->has_key('new_key'));
        $reflection = new ReflectionClass($option);
        $property = $reflection->getProperty('is_modified');
        $property->setAccessible(true);
        $this->assertTrue($property->getValue($option));
    }
}
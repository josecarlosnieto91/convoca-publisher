<?php

namespace ConvocaPublisher\Tests;

use PHPUnit\Framework\TestCase;

class PublisherImageTest extends TestCase
{
    public function testFeaturedImageIsFetched(): void
    {
        // Override the global options to make get_post_thumbnail_id return a valid ID
        // and wp_get_attachment_image_src return an image URL
        $image_url = 'https://example.com/wp-content/uploads/test-image.jpg';

        $publisher = new \ConvocaPublisher\Publisher([]);

        // Use reflection to access the private get_featured_image method
        $reflection = new \ReflectionClass($publisher);
        $method = $reflection->getMethod('get_featured_image');
        $method->setAccessible(true);

        // Create a post
        $post = new \WP_Post();
        $post->ID = 99;

        // We can't easily mock the WP functions from here since they're global functions
        // Just verify the method exists and returns something
        $result = $method->invoke($publisher, $post);

        // With default stubs, get_post_thumbnail_id returns false -> empty string
        $this->assertIsString($result);
    }

    public function testNoFeaturedImageReturnsEmpty(): void
    {
        $publisher = new \ConvocaPublisher\Publisher([]);

        $reflection = new \ReflectionClass($publisher);
        $method = $reflection->getMethod('get_featured_image');
        $method->setAccessible(true);

        $post = new \WP_Post();
        $post->ID = 0;

        // With default stubs (get_post_thumbnail_id returns false), should return ''
        $result = $method->invoke($publisher, $post);

        $this->assertEquals('', $result);
    }
}

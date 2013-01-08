<?php

namespace React\FunctionnalTests\Stomp;

class basicTest extends TestCase
{
    /** @test */
    public function itShouldConnect()
    {
        $loop = $this->getEventLoop();
        $client = $this->getClient($loop);

        $phpunit = $this;
        $connected = false;

        $client
            ->connect()
            ->then(function () use ($loop, &$connected) {
                $connected = true;
                $loop->stop();
            }, function (\Exception $e) use ($phpunit) {
                $phpunit->fail('Connection should occur');
            });

        $loop->run();

        $this->assertTrue($connected);
    }

    /** @test */
    public function itShouldFailOnConnect()
    {
        $loop = $this->getEventLoop();
        $client = $this->getClient($loop, array(
            'login' => 'badidealogin',
            'passcode' => 'thereisnoprobabilitythatyouusethispassword'
        ));

        $phpunit = $this;
        $error = null;

        $client
            ->connect()
            ->then(function () use ($phpunit) {
                $phpunit->fail('Connection should occur');
            }, function ($e) use ($loop, &$error) {
                $error = $e;
                $loop->stop();
            });

        $loop->run();

        $this->assertInstanceOf('Exception', $error);
    }

    /** @test */
    public function itShouldReceiveOnSubscribedTopicsWhatItSends()
    {
        $loop = $this->getEventLoop();
        $client = $this->getClient($loop);

        $phpunit = $this;
        $received = false;

        $client
            ->connect()
            ->then(function ($client) use ($loop, $phpunit, &$received) {
                $client->subscribe('/topic/foo', function ($frame) use ($phpunit, &$received, $loop) {
                    $phpunit->assertEquals('le message à la papa', $frame->body);
                    $received = true;
                    $loop->stop();
                });

                $client->send('/topic/foo', 'le message à la papa');
            });

        $loop->run();
        $this->assertTrue($received);
    }

    /** @test */
    public function itShouldReceiveAgainNackedMessages()
    {
        $this->markTestSkipped('Temporary disabling this test as Apollo hang on it');

        $loop = $this->getEventLoop();
        $client = $this->getClient($loop);

        $counter = 0;

        $client
            ->connect()
            ->then(function ($client) use ($loop, &$counter) {
                $client->subscribeWithAck('/topic/foo', 'client-individual', function ($frame, $resolver) use ($loop, &$counter) {
                    if (0 === $counter) {
                        $resolver->nack();
                    } else {
                        $resolver->ack();
                        $loop->stop();
                    }
                    $counter++;
                });

                $client->send('/topic/foo', 'le message à la papa');
            });

        $loop->run();
        $this->assertEquals(2, $counter);
    }
}

<?php

namespace Dias\Tests\Modules\CopriaColorSort\Http\Controllers\Api;

use ApiTestCase;
use Dias\Tests\ImageTest;
use Dias\Modules\Copria\ApiToken;
use Dias\Modules\Copria\PipelineCallback;
use Dias\Modules\Copria\ColorSort\Transect;
use Dias\Modules\Copria\ColorSort\Sequence;
use Dias\Tests\Modules\CopriaColorSort\SequenceTest;
use Dias\Modules\Copria\ColorSort\Jobs\ExecuteNewSequencePipeline;

class TransectColorSortSequenceControllerTest extends ApiTestCase
{
    public function testIndex()
    {
        $transect = $this->transect();
        $id = $transect->id;

        $s1 = SequenceTest::make(['transect_id' => $transect->id]);
        $s1->sequence = [1, 2];
        $s1->save();
        $s2 = SequenceTest::create(['transect_id' => $transect->id]);

        $this->doTestApiRoute('GET', "/api/v1/transects/{$id}/color-sort-sequence");

        $this->beUser();
        $this->get("/api/v1/transects/{$id}/color-sort-sequence")
            ->assertResponseStatus(403);

        $this->beGuest();
        $this->get("/api/v1/transects/{$id}/color-sort-sequence")
            // show only sequences with actual sorting data
            ->seeJsonEquals([$s1->color]);
    }

    public function testShow()
    {
        $transect = $this->transect();
        $id = $transect->id;

        $s = SequenceTest::create(['transect_id' => $transect->id]);

        $this->doTestApiRoute('GET', "/api/v1/transects/{$id}/color-sort-sequence/{$s->color}");

        $this->beUser();
        $this->get("/api/v1/transects/{$id}/color-sort-sequence/{$s->color}")
            ->assertResponseStatus(403);

        $this->beGuest();
        $this->get("/api/v1/transects/{$id}/color-sort-sequence/abc")
            ->assertResponseStatus(404);

        $this->get("/api/v1/transects/{$id}/color-sort-sequence/{$s->color}");
        // exists but computing is not finished yet
        $this->assertEmpty($this->response->getContent());

        $s->sequence = [1, 3, 2];
        $s->save();

        $this->get("/api/v1/transects/{$id}/color-sort-sequence/{$s->color}")
            ->seeJsonEquals([1, 3, 2]);
    }

    public function testStore()
    {
        $transect = Transect::find($this->transect()->id);
        $id = $transect->id;

        $this->doTestApiRoute('POST', "/api/v1/transects/{$id}/color-sort-sequence");

        // guests cannot request new color sort sequences
        $this->beGuest();
        $token = new ApiToken;
        $token->owner()->associate($this->guest());
        $token->token = 'abcd';
        $token->save();
        $this->post("/api/v1/transects/{$id}/color-sort-sequence", [
            'color' => 'abcdef',
        ]);
        $this->assertResponseStatus(403);

        $this->beEditor();

        $this->post("/api/v1/transects/{$id}/color-sort-sequence", [
            'color' => 'c0ffee',
        ]);
        // user has no Copria account connected
        $this->assertResponseStatus(401);

        $token = new ApiToken;
        $token->owner()->associate($this->editor());
        $token->token = 'abcd';
        $token->save();

        // missing color
        $this->json('POST', "/api/v1/transects/{$id}/color-sort-sequence");
        $this->assertResponseStatus(422);

        // invalid color
        $this->json('POST', "/api/v1/transects/{$id}/color-sort-sequence", [
            'color' => 'bcdefg',
        ]);
        $this->assertResponseStatus(422);
        $this->json('POST', "/api/v1/transects/{$id}/color-sort-sequence", [
            'color' => 'abcdef1',
        ]);
        $this->assertResponseStatus(422);

        $this->expectsJobs(ExecuteNewSequencePipeline::class);

        $this->assertEquals(0, $transect->colorSortSequences()->count());
        $this->post("/api/v1/transects/{$id}/color-sort-sequence", [
            'color' => 'bada55',
        ])->assertResponseOk();
        $this->assertEquals(1, $transect->colorSortSequences()->count());
        $this->assertEquals('bada55', $transect->colorSortSequences()->first()->color);

        // requesting the same color twice is not allowed
        $this->post("/api/v1/transects/{$id}/color-sort-sequence", [
            'color' => 'bada55',
        ])->assertResponseStatus(405);
    }

    public function testStoreRemote()
    {
        $transect = Transect::find($this->transect()->id);
        $id = $transect->id;
        $transect->url = 'http://localhost';
        $transect->save();

        $this->beEditor();
        $token = new ApiToken;
        $token->owner()->associate($this->editor());
        $token->token = 'abcd';
        $token->save();

        $this->doesntExpectJobs(ExecuteNewSequencePipeline::class);

        $this->json('POST', "/api/v1/transects/{$id}/color-sort-sequence", [
            'color' => 'bada55',
        ]);
        $this->assertResponseStatus(422);
    }

    public function testResultMalformed()
    {

        $sequence = SequenceTest::create();

        $callback = new PipelineCallback;
        $callback->generateToken();
        $callback->function = 'Dias\Modules\Copria\ColorSort\Http\Controllers\Api\TransectColorSortSequenceController@result';
        $callback->payload = ['id' => $sequence->id];
        $callback->save();

        // image ids are missing
        $this->post("api/v1/copria-pipeline-callback/{$callback->token}")
            ->assertResponseStatus(422);
    }

    public function testResultFailed()
    {

        $sequence = SequenceTest::create();

        $callback = new PipelineCallback;
        $callback->generateToken();
        $callback->function = 'Dias\Modules\Copria\ColorSort\Http\Controllers\Api\TransectColorSortSequenceController@result';
        $callback->payload = ['id' => $sequence->id];
        $callback->save();

        // job failed, delete color sort sequence
        $this->post("api/v1/copria-pipeline-callback/{$callback->token}", ['state' => []])
            ->assertResponseOk();
        $this->assertNull($sequence->fresh());
    }

    public function testResultSuccess()
    {
        $sequence = SequenceTest::create();

        $callback = new PipelineCallback;
        $callback->generateToken();
        $callback->function = 'Dias\Modules\Copria\ColorSort\Http\Controllers\Api\TransectColorSortSequenceController@result';
        $callback->payload = ['id' => $sequence->id];
        $callback->save();

        $image1 = ImageTest::create(['transect_id' => $sequence->transect->id, 'filename' => 'a']);
        $image2 = ImageTest::create(['transect_id' => $sequence->transect->id, 'filename' => 'b']);

        // job succeeded, set sorting order
        $this->post("api/v1/copria-pipeline-callback/{$callback->token}", ['pin1' => $image2->id.',300,200,'.$image1->id])
            ->assertResponseOk();
        // the sequence should only contain image IDs that actually belong to the transect
        // the ordering must be kept, though
        $this->assertEquals([$image2->id, $image1->id], $sequence->fresh()->sequence);
    }
}
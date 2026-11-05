<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\BlogModuleModel;
use App\Models\ModuleModel;
use App\Models\UserModel;
use Framework\Exceptions\PageNotFoundException;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;
use Tests\Fixtures\Modules\SampleExtra\NoteController;
use Tests\Helpers\ModuleFixtures;

beforeEach(function () {
    ModuleFixtures::loadSampleExtra();
    (new ModuleModel($this->db))->record('sample-extra', 1);

    $ownerId = UserFactory::new(new UserModel($this->db))->create();
    $this->blogId = BlogFactory::new(new BlogModel($this->db))->create($ownerId);
    $this->blogModules = new BlogModuleModel($this->db);
    $this->controller = new NoteController($this->blogModules);
});

test('a module page is not found on a blog that has the module off', function () {
    expect(fn () => $this->controller->checkBlog($this->blogId))->toThrow(PageNotFoundException::class);
});

test('a module page opens on a blog that switched the module on', function () {
    $this->blogModules->enable($this->blogId, 'sample-extra');

    $this->controller->checkBlog($this->blogId);

    expect(true)->toBeTrue();
});

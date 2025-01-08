<?php

namespace CDash\Test;

use CDash\Model\Build;
use CDash\ServiceContainer;
use CDash\Test\UseCase\UseCase;

class CDashUseCaseTestCase extends CDashTestCase
{
    /** @var ServiceContainer */
    private $originalServiceContainer;

    public function tearDown(): void
    {
        if ($this->originalServiceContainer) {
            ServiceContainer::setInstance(
                ServiceContainer::class,
                $this->originalServiceContainer
            );
        }
        parent::tearDown(); // TODO: Change the autogenerated stub
    }

    public function setUseCaseModelFactory(UseCase $useCase)
    {
        $this->setDatabaseMocked();
        $this->originalServiceContainer = ServiceContainer::getInstance();

        $mockServiceContainer = $this->getMockBuilder(ServiceContainer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();

        $mockServiceContainer
            ->expects($this->any())
            ->method('create')
            ->willReturnCallback(function ($class_name) use ($useCase) {
                $methods = [];
                foreach (['Insert', 'Update', 'Save', 'GetCommitAuthors', 'GetMissingTests'] as $method) {
                    if (method_exists($class_name, $method)) {
                        $methods[] = $method;
                    }
                }

                $model = $this->getMockBuilder($class_name)
                    ->onlyMethods($methods)
                    ->getMock();

                if (method_exists($class_name, 'Save')) {
                    $model->expects($this->any())
                        ->method('Save')
                        ->willReturnCallback(function () use ($class_name, $model, $useCase) {
                            $model->Id = $useCase->getIdForClass($class_name);
                            if (isset($model->Errors)) {
                                foreach ($model->Errors as $error) {
                                    $error->BuildId = $model->Id;
                                }
                            }
                            return $model->Id;
                        });
                }

                if (method_exists($class_name, 'Insert')) {
                    $model->expects($this->any())
                        ->method('Insert')
                        ->willReturnCallback(function () use ($class_name, $model, $useCase) {
                            // TODO: discuss
                            if (!property_exists($model, 'Id')) {
                                $model->Id = null;
                            }

                            if (!$model->Id) {
                                $model->Id = $useCase->getIdForClass($class_name);
                            }
                            return $model->Id;
                        });
                }

                if (method_exists($class_name, 'GetCommitAuthors')) {
                    $model->expects($this->any())
                        ->method('GetCommitAuthors')
                        ->willReturnCallback(function () use ($useCase, $model) {
                            /* @var Build|\PHPUnit\Framework\MockObject\MockObject $model */
                            return $useCase->getAuthors($model->SubProjectName);
                        });
                }

                if (method_exists($class_name, 'GetMissingTests')) {
                    $model->expects($this->any())
                        ->method('GetMissingTests')
                        ->willReturnCallback(function () use ($useCase) {
                            $missing = [];
                            if (isset($useCase->missingTests)) {
                                $missing = $useCase->missingTests;
                            }
                            return $missing;
                        });
                }

                return $model;
            });

        ServiceContainer::setInstance(ServiceContainer::class, $mockServiceContainer);
    }
}

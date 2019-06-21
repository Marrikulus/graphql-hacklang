<?hh //strict
//decl

namespace GraphQL\Tests\Executor;

use GraphQL\Deferred;
use function Facebook\FBExpect\expect;
use GraphQL\Error\UserError;
use GraphQL\Executor\Executor;
use GraphQL\Error\FormattedError;
use GraphQL\Language\Parser;
use GraphQL\Language\SourceLocation;
use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;

class NonNullTest extends \Facebook\HackTest\HackTest
{
    /** @var \Exception */
    public $syncError;

    /** @var \Exception */
    public $nonNullSyncError;

    /** @var  \Exception */
    public $promiseError;

    /** @var  \Exception */
    public $nonNullPromiseError;

    public $throwingData;
    public $nullingData;
    public $schema;

    public async function beforeEachTestAsync(): Awaitable<void>
    {
        $this->syncError = new UserError('sync');
        $this->nonNullSyncError = new UserError('nonNullSync');
        $this->promiseError = new UserError('promise');
        $this->nonNullPromiseError = new UserError('nonNullPromise');

        $this->throwingData = [
            'sync' => function () {
                throw $this->syncError;
            },
            'nonNullSync' => function () {
                throw $this->nonNullSyncError;
            },
            'promise' => function () {
                return new Deferred(function () {
                    throw $this->promiseError;
                });
            },
            'nonNullPromise' => function () {
                return new Deferred(function () {
                    throw $this->nonNullPromiseError;
                });
            },
            'nest' => function () {
                return $this->throwingData;
            },
            'nonNullNest' => function () {
                return $this->throwingData;
            },
            'promiseNest' => function () {
                return new Deferred(function () {
                    return $this->throwingData;
                });
            },
            'nonNullPromiseNest' => function () {
                return new Deferred(function () {
                    return $this->throwingData;
                });
            },
        ];

        $this->nullingData = [
            'sync' => function () {
                return null;
            },
            'nonNullSync' => function () {
                return null;
            },
            'promise' => function () {
                return new Deferred(function () {
                    return null;
                });
            },
            'nonNullPromise' => function () {
                return new Deferred(function () {
                    return null;
                });
            },
            'nest' => function () {
                return $this->nullingData;
            },
            'nonNullNest' => function () {
                return $this->nullingData;
            },
            'promiseNest' => function () {
                return new Deferred(function () {
                    return $this->nullingData;
                });
            },
            'nonNullPromiseNest' => function () {
                return new Deferred(function () {
                    return $this->nullingData;
                });
            },
        ];

        $dataType = new ObjectType([
            'name' => 'DataType',
            'fields' => function() use (&$dataType) {
                return [
                    'sync' => ['type' => GraphQlType::string()],
                    'nonNullSync' => ['type' => GraphQlType::nonNull(GraphQlType::string())],
                    'promise' => GraphQlType::string(),
                    'nonNullPromise' => GraphQlType::nonNull(GraphQlType::string()),
                    'nest' => $dataType,
                    'nonNullNest' => GraphQlType::nonNull($dataType),
                    'promiseNest' => $dataType,
                    'nonNullPromiseNest' => GraphQlType::nonNull($dataType),
                ];
            }
        ]);

        $this->schema = new Schema(['query' => $dataType]);
    }

    // Execute: handles non-nullable types

    /**
     * @it nulls a nullable field that throws synchronously
     */
    public function testNullsANullableFieldThatThrowsSynchronously():void
    {
        $doc = '
      query Q {
        sync
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'sync' => null,
            ],
            'errors' => [
                FormattedError::create(
                    $this->syncError->getMessage(),
                    [new SourceLocation(3, 9)]
                )
            ]
        ];
        $result = Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray();
        expect($result)->toInclude($expected);
    }

    public function testNullsANullableFieldThatThrowsInAPromise():void
    {
        $doc = '
      query Q {
        promise
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'promise' => null,
            ],
            'errors' => [
                FormattedError::create(
                    $this->promiseError->getMessage(),
                    [new SourceLocation(3, 9)]
                )
            ]
        ];
        $result = Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray();
        expect($result)->toInclude($expected);
    }

    public function testNullsASynchronouslyReturnedObjectThatContainsANonNullableFieldThatThrowsSynchronously():void
    {
        // nulls a synchronously returned object that contains a non-nullable field that throws synchronously
        $doc = '
      query Q {
        nest {
          nonNullSync,
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => null
            ],
            'errors' => [
                FormattedError::create($this->nonNullSyncError->getMessage(), [new SourceLocation(4, 11)])
            ]
        ];      $result = Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray();
        expect($result)->toInclude($expected);
    }

    public function testNullsAsynchronouslyReturnedObjectThatContainsANonNullableFieldThatThrowsInAPromise():void
    {
        $doc = '
      query Q {
        nest {
          nonNullPromise,
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => null
            ],
            'errors' => [
                FormattedError::create($this->nonNullPromiseError->getMessage(), [new SourceLocation(4, 11)])
            ]
        ];
        $result = Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray();
        expect($result)->toInclude($expected);
    }

    public function testNullsAnObjectReturnedInAPromiseThatContainsANonNullableFieldThatThrowsSynchronously():void
    {
        $doc = '
      query Q {
        promiseNest {
          nonNullSync,
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'promiseNest' => null
            ],
            'errors' => [
                FormattedError::create($this->nonNullSyncError->getMessage(), [new SourceLocation(4, 11)])
            ]
        ];
        $result = Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray();
        expect($result)->toInclude($expected);
    }

    public function testNullsAnObjectReturnedInAPromiseThatContainsANonNullableFieldThatThrowsInAPromise():void
    {
        $doc = '
      query Q {
        promiseNest {
          nonNullPromise,
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'promiseNest' => null
            ],
            'errors' => [
                FormattedError::create($this->nonNullPromiseError->getMessage(), [new SourceLocation(4, 11)])
            ]
        ];
        $result = Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray();
        expect($result)->toInclude($expected);
    }

    /**
     * @it nulls a complex tree of nullable fields that throw
     */
    public function testNullsAComplexTreeOfNullableFieldsThatThrow():void
    {
        $doc = '
      query Q {
        nest {
          sync
          promise
          nest {
            sync
            promise
          }
          promiseNest {
            sync
            promise
          }
        }
        promiseNest {
          sync
          promise
          nest {
            sync
            promise
          }
          promiseNest {
            sync
            promise
          }
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => [
                    'sync' => null,
                    'promise' => null,
                    'nest' => [
                        'sync' => null,
                        'promise' => null,
                    ],
                    'promiseNest' => [
                        'sync' => null,
                        'promise' => null,
                    ],
                ],
                'promiseNest' => [
                    'sync' => null,
                    'promise' => null,
                    'nest' => [
                        'sync' => null,
                        'promise' => null,
                    ],
                    'promiseNest' => [
                        'sync' => null,
                        'promise' => null,
                    ],
                ],
            ],
            'errors' => [
                FormattedError::create($this->syncError->getMessage(), [new SourceLocation(4, 11)]),
                FormattedError::create($this->syncError->getMessage(), [new SourceLocation(7, 13)]),
                FormattedError::create($this->syncError->getMessage(), [new SourceLocation(11, 13)]),
                FormattedError::create($this->syncError->getMessage(), [new SourceLocation(16, 11)]),
                FormattedError::create($this->syncError->getMessage(), [new SourceLocation(19, 13)]),
                FormattedError::create($this->promiseError->getMessage(), [new SourceLocation(5, 11)]),
                FormattedError::create($this->promiseError->getMessage(), [new SourceLocation(8, 13)]),
                FormattedError::create($this->syncError->getMessage(), [new SourceLocation(23, 13)]),
                FormattedError::create($this->promiseError->getMessage(), [new SourceLocation(12, 13)]),
                FormattedError::create($this->promiseError->getMessage(), [new SourceLocation(17, 11)]),
                FormattedError::create($this->promiseError->getMessage(), [new SourceLocation(20, 13)]),
                FormattedError::create($this->promiseError->getMessage(), [new SourceLocation(24, 13)]),
            ]
        ];
        $result = Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray();
        expect($result)->toInclude($expected);
    }

    public function testNullsTheFirstNullableObjectAfterAFieldThrowsInALongChainOfFieldsThatAreNonNull():void
    {
        $doc = '
      query Q {
        nest {
          nonNullNest {
            nonNullPromiseNest {
              nonNullNest {
                nonNullPromiseNest {
                  nonNullSync
                }
              }
            }
          }
        }
        promiseNest {
          nonNullNest {
            nonNullPromiseNest {
              nonNullNest {
                nonNullPromiseNest {
                  nonNullSync
                }
              }
            }
          }
        }
        anotherNest: nest {
          nonNullNest {
            nonNullPromiseNest {
              nonNullNest {
                nonNullPromiseNest {
                  nonNullPromise
                }
              }
            }
          }
        }
        anotherPromiseNest: promiseNest {
          nonNullNest {
            nonNullPromiseNest {
              nonNullNest {
                nonNullPromiseNest {
                  nonNullPromise
                }
              }
            }
          }
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => null,
                'promiseNest' => null,
                'anotherNest' => null,
                'anotherPromiseNest' => null,
            ],
            'errors' => [
                FormattedError::create($this->nonNullSyncError->getMessage(), [new SourceLocation(8, 19)]),
                FormattedError::create($this->nonNullSyncError->getMessage(), [new SourceLocation(19, 19)]),
                FormattedError::create($this->nonNullPromiseError->getMessage(), [new SourceLocation(30, 19)]),
                FormattedError::create($this->nonNullPromiseError->getMessage(), [new SourceLocation(41, 19)]),
            ]
        ];
        $result = Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray();
        expect($result)->toInclude($expected);
    }

    public function testNullsANullableFieldThatSynchronouslyReturnsNull():void
    {
        $doc = '
      query Q {
        sync
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'sync' => null,
            ]
        ];
        expect(Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray())->toBePHPEqual($expected);
    }

    public function testNullsANullableFieldThatReturnsNullInAPromise():void
    {
        $doc = '
      query Q {
        promise
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'promise' => null,
            ]
        ];
        $result = Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray();
        expect($result)->toInclude($expected);
    }

    public function testNullsASynchronouslyReturnedObjectThatContainsANonNullableFieldThatReturnsNullSynchronously():void
    {
        $doc = '
      query Q {
        nest {
          nonNullSync,
        }
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => null
            ],
            'errors' => [
                [
                    'debugMessage' => 'Cannot return null for non-nullable field DataType.nonNullSync.',
                    'locations' => [['line' => 4, 'column' => 11]]
                ]
            ]
        ];      $result = Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray(1);
        expect($result)->toInclude($expected);
    }

    public function testNullsASynchronouslyReturnedObjectThatContainsANonNullableFieldThatReturnsNullInAPromise():void
    {
        $doc = '
      query Q {
        nest {
          nonNullPromise,
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => null,
            ],
            'errors' => [
                [
                    'debugMessage' => 'Cannot return null for non-nullable field DataType.nonNullPromise.',
                    'locations' => [['line' => 4, 'column' => 11]]
                ],
            ]
        ];

        $result = Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray(1);
        expect($result)->toInclude($expected);
    }

    public function testNullsAnObjectReturnedInAPromiseThatContainsANonNullableFieldThatReturnsNullSynchronously():void
    {
        $doc = '
      query Q {
        promiseNest {
          nonNullSync,
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'promiseNest' => null,
            ],
            'errors' => [
                [
                    'debugMessage' => 'Cannot return null for non-nullable field DataType.nonNullSync.',
                    'locations' => [['line' => 4, 'column' => 11]]
                ],
            ]
        ];

        $result = Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray(1);
        expect($result)->toInclude($expected);
    }

    public function testNullsAnObjectReturnedInAPromiseThatContainsANonNullableFieldThatReturnsNullInaAPromise():void
    {
        $doc = '
      query Q {
        promiseNest {
          nonNullPromise,
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'promiseNest' => null,
            ],
            'errors' => [
                [
                    'debugMessage' => 'Cannot return null for non-nullable field DataType.nonNullPromise.',
                    'locations' => [['line' => 4, 'column' => 11]]
                ],
            ]
        ];

        $result = Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray(1);
        expect($result)->toInclude($expected);
    }

    public function testNullsAComplexTreeOfNullableFieldsThatReturnNull():void
    {
        $doc = '
      query Q {
        nest {
          sync
          promise
          nest {
            sync
            promise
          }
          promiseNest {
            sync
            promise
          }
        }
        promiseNest {
          sync
          promise
          nest {
            sync
            promise
          }
          promiseNest {
            sync
            promise
          }
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => [
                    'sync' => null,
                    'promise' => null,
                    'nest' => [
                        'sync' => null,
                        'promise' => null,
                    ],
                    'promiseNest' => [
                        'sync' => null,
                        'promise' => null,
                    ]
                ],
                'promiseNest' => [
                    'sync' => null,
                    'promise' => null,
                    'nest' => [
                        'sync' => null,
                        'promise' => null,
                    ],
                    'promiseNest' => [
                        'sync' => null,
                        'promise' => null,
                    ]
                ]
            ]
        ];

        $actual = Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray();
        expect($actual)->toBePHPEqual($expected);
    }

    public function testNullsTheFirstNullableObjectAfterAFieldReturnsNullInALongChainOfFieldsThatAreNonNull():void
    {
        $doc = '
      query Q {
        nest {
          nonNullNest {
            nonNullPromiseNest {
              nonNullNest {
                nonNullPromiseNest {
                  nonNullSync
                }
              }
            }
          }
        }
        promiseNest {
          nonNullNest {
            nonNullPromiseNest {
              nonNullNest {
                nonNullPromiseNest {
                  nonNullSync
                }
              }
            }
          }
        }
        anotherNest: nest {
          nonNullNest {
            nonNullPromiseNest {
              nonNullNest {
                nonNullPromiseNest {
                  nonNullPromise
                }
              }
            }
          }
        }
        anotherPromiseNest: promiseNest {
          nonNullNest {
            nonNullPromiseNest {
              nonNullNest {
                nonNullPromiseNest {
                  nonNullPromise
                }
              }
            }
          }
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => null,
                'promiseNest' => null,
                'anotherNest' => null,
                'anotherPromiseNest' => null,
            ],
            'errors' => [
                ['debugMessage' => 'Cannot return null for non-nullable field DataType.nonNullSync.', 'locations' => [ ['line' => 8, 'column' => 19]]],
                ['debugMessage' => 'Cannot return null for non-nullable field DataType.nonNullSync.', 'locations' => [ ['line' => 19, 'column' => 19]]],
                ['debugMessage' => 'Cannot return null for non-nullable field DataType.nonNullPromise.', 'locations' => [ ['line' => 30, 'column' => 19]]],
                ['debugMessage' => 'Cannot return null for non-nullable field DataType.nonNullPromise.', 'locations' => [ ['line' => 41, 'column' => 19]]],
            ]
        ];

        $result = Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray(1);
        expect($result)->toInclude($expected);
    }

    /**
     * @it nulls the top level if sync non-nullable field throws
     */
    public function testNullsTheTopLevelIfSyncNonNullableFieldThrows():void
    {
        $doc = '
      query Q { nonNullSync }
        ';

        $expected = [
            'data' => null,
            'errors' => [
                FormattedError::create($this->nonNullSyncError->getMessage(), [new SourceLocation(2, 17)])
            ]
        ];
        $actual = Executor::execute($this->schema, Parser::parse($doc), $this->throwingData)->toArray();
        expect($actual)->toInclude($expected);
    }

    public function testNullsTheTopLevelIfAsyncNonNullableFieldErrors():void
    {
        $doc = '
      query Q { nonNullPromise }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => null,
            'errors' => [
                FormattedError::create($this->nonNullPromiseError->getMessage(), [new SourceLocation(2, 17)]),
            ]
        ];
        $result = Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray();
        expect($result)->toInclude($expected);
    }

    public function testNullsTheTopLevelIfSyncNonNullableFieldReturnsNull():void
    {
        // nulls the top level if sync non-nullable field returns null
        $doc = '
      query Q { nonNullSync }
        ';

        $expected = [
            'errors' => [
                [
                    'debugMessage' => 'Cannot return null for non-nullable field DataType.nonNullSync.',
                    'locations' => [['line' => 2, 'column' => 17]]
                ],
            ]
        ];
        $result = Executor::execute($this->schema, Parser::parse($doc), $this->nullingData)->toArray(1);
        expect($result)->toInclude($expected);
    }

    public function testNullsTheTopLevelIfAsyncNonNullableFieldResolvesNull():void
    {
        $doc = '
      query Q { nonNullPromise }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => null,
            'errors' => [
                [
                    'debugMessage' => 'Cannot return null for non-nullable field DataType.nonNullPromise.',
                    'locations' => [['line' => 2, 'column' => 17]]
                ],
            ]
        ];

        $result = Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray(1);
        expect($result)->toInclude($expected);
    }
}

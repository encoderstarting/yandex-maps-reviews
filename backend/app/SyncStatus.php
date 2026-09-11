<?php

namespace App;

enum SyncStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Blocked = 'blocked';
    case SourceChanged = 'source_changed';
}

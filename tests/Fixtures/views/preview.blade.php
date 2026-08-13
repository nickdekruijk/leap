record:{{ $record->title }}
body:{{ $record->body }}
locale:{{ app()->getLocale() }}
active:{{ $record->active ? 'yes' : 'no' }}
preview:{{ \NickDeKruijk\Leap\Leap::isPreview() ? 'yes' : 'no' }}
unsaved:{{ \NickDeKruijk\Leap\Leap::previewIsUnsaved() ? 'yes' : 'no' }}

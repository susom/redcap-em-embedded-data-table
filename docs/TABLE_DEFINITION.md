# Table Definition Syntax

```javascript

{
    "table_name" : {
        "project_id"            : "55 (could be same as current project or a different project)",
        "arm_id"                : "",
        "event_id"              : "",
        "form"                  : "(only for type=repeatingForm)",
        // LINK REQUIRED IF DATA ABOVE IS IN DIFFERENT PROJECT OR ARM
        "link"                  : {
            "source_field"          : "record_id",      // Name of field in current project - can only have 1 value (cannot be repeating)
            "source_event_id"       : "15",             // Event in current project where key field resides (not required if source_field is primary key)
            "other_field"           : "fk_record_id",   // Name of field in current project
        }

        "data_type"             : "repeatingForm | repeatingEvent | file",
        "title"                 : "Display title",
        "fields"                : [
            "field_1": "Field 1 Label",
            "field_2": "Field 2 label",
        ]
    }
}

```

#### Example 1:
Data is in current project as repeating form in a single event
```javascript
{
    "table_name" : {
        "project_id"            : "55", // Current project_id
        "arm_id"                : "1",
        "event_id"              : "15",
        "data_type"             : "repeatingForm",
        "repeating_form"        : "form name",
        "title"                 : "Display title",
        "fields"                : [
            "field_1": "Field 1 Label",
            "field_2": "Field 2 label",
        ]
    }
}
```

#### Example 2:
Data is in current project as repeating form across all events
Example would be medication form that is repeating in Visit1 and Visit2 events
// TODO: Not supported
```javascript
{
    "table_name" : {
        "project_id"            : "55", // Current project_id
        "arm_id"                : "1",
        "event_id"              : "",
        "data_type"             : "repeatingForm",
        "repeating_form"        : "form name",
        "title"                 : "Display title",
        "fields"                : [
            "field_1": "Field 1 Label",
            "field_2": "Field 2 label",
        ]
    }
}
```

#### Example 3:
Data is in current project as repeating event
Example would be multiple CATs given for each visit and summarize aggregate scores in table.
```javascript
{
    "table_name" : {
        "project_id"            : "55",             // Current project_id
        "arm_id"                : "1",
        "event_id"              : "15",             // Event_id being repeated
        "data_type"             : "repeatingEvent",
        "repeating_form"        : "",
        "title"                 : "Display title",
        "fields"                : [
            // Works with fields from many forms so long as they are all enabled in repeating event_id
            "field_1": "Field 1 Label",
            "field_2": "Field 2 label",
        ]
    }
}
```

#### Example 4:
Data is in a different project or a different ARM of the same project - so we need a filter to records from the other project/arm first
```javascript
{
    "table_name" : {
        "project_id"            : "65",     // Other project_id
        "arm_id"                : "1",      // Arm in other project
        "event_id"              : "",       // (optional) to limit data to specific event in other project
        "link"                  : {
            // Link / Mapping required because data comes from a different project or arm in the same project
            "source_field"          : "record_id",      // Name of field in current project - can only have 1 value (cannot be repeating)
            "source_event_id"       : "15",             // Event in current project where key field resides (not required if source_field is primary key)
            "other_field"           : "fk_record_id",   // Name of field in current project
        }, 
        "data_type"             : "repeatingEvent",
        "repeating_form"        : "",
        "title"                 : "Display title",
        "fields"                : [
            // Works with fields from many forms so long as they are all enabled in repeating event_id
            "field_1": "Field 1 Label",
            "field_2": "Field 2 label",
        ]
    }
}
```



#### Example 5:
Data is generated from a custom data source with a custom php function - used for per-project customizations
```javascript
{
    "table_name" : {
        "file"                  : "file_path"   // (it is assumed that all files live inside a a system setting (data_table_custom_file_root) - e.g. motif/func_a.php or motif_func_a.php)
    }
}
```

custom_report()

// custom_report.php





## Data Types

### repeatingForm

### repeatingEvent

### file
The table will be dervived entirely from another php file which is included and must define a function by the same name as the file.

e.g. /plugins/custom_datatables/table_a.php would define a function like:
```php
    function table_a($project_id, $record_id, $event_id) {}
```
It is required that this function return a valid array as:
 returns
```php
[
    "title"  => "foo",
    "header" => ["col1","col2","..."],
    "data"   => [
        ["a1", "b1", "c1..."],
        ["a2", "b2", "c2..."],
        ...
    ]
]
```

```php
/custom_report_prefix/motifCustomReport.php

<?php
    namespace Stanford\EDT;
    class MotifCustomReport implements \Stanford\EDT\CustomReportInterface {

        function __construct($project_id, $record, $arm_id = null, $event_id = null) {
        }

        function getTitle() {
        }

        function getHeader() {
        }

        function getData() {
        }

    }
>
```





### key

## Embedded Data Tables (EDT)

The EDT external module will allow users the ability to specify columns for a display table and enable the custom table in their project. 

The following scenarios are supported:

    When data is located in the same project as the display:
    * Data can be displayed from repeating forms
    * Data can be displayed across events (must be in the same arm for multi-arm projects)
    
    When data is located in a different project but the display will be in the current project
    * Data can be displayed from a repeating form
    * Data can be displayed from data across events (must be in the same arm)
    * Linked records in the data project can be displayed (i.e. diary projects)
    
    The following scenarios are not currently supported:
    * Data across events when there are repeating forms in an event
    * Data from multiple forms in repeating events


## Setup
When this External Module is enabled for a project, a link to the EDT Setup page is located in the External Module section. The following pieces of data are required to specify a data table:

    * Configuration Name
    * Project ID (selected from dropdown) where the data is located
    * If the project is multi-arm, Arm to use for data retrieval (selected from dropdown)
    * The type of data you want to display (i.e. across events, repeating forms, linkage key diary project)
    * If the data project is not the current project, select the field in the data project which holds the linkage key to the current project
    * Select the fields to display in the table (in the order of display)

Once this information is specified, the configuration parameters will be stored in the database for the display.

## Display
The currently implemented way to access the display page is a project bookmark. The bookmark URL is

    http://localhost/redcap/redcap_v8.10.1/ExternalModules/?prefix=embedded-data-tables&page=pages%2Fdisplay
             (on my local machine)
  OR
  
    https://redcap-dev.stanford.edu/redcap_v8.11.3/ExternalModules/?prefix=embedded-data-tables&page=pages%2Fdisplay
             (on redcap-dev)

The project ID and record ID must be included for the display to work.

In the future, users will be able to specify fields in an instrument before which the display will be inserted.

TED Support for EmonCMS
=======================

This plugin allows emoncms to capture data from a The Energy Detective device.

Installation
------------

To add this plugin, clone this repo inside of the `Modules` folder in your
installation of emoncms.

    cd Modules
    git clone https://github.com/lab11/emoncms-ted

Usage
-----

To set up your TED to report to emoncms, follow these steps:

1. Setup the TED gateway as a device in emoncms. Go to the "Device Setup"
page and create a new device. In the "Node" column enter the gateway
ID. This can be found on the TED configuration page under "System Settings",
"Product Identification", "ECC Product ID". In the "Device access key" column
edit the provided value until it is 13 characters long. Save these changes.

2. Configure TED to POST to a third party service. On the TED configuration
page, select "Settings", "Activate Energy Posting". In the URL box, enter

        http://<emoncms server>/ted/post.text
        
  and in the unique identifier box, enter the 13 character string from
  the emomcms device. After clicking submit you should see
  the TED start posting data to emoncms every two minutes.


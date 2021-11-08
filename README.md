**To Update Production:**

1. Connect to salt master `ssh user@10.100.12.32`
1. Switch to root user `sudo su root`
1. Navigate to salt repo `cd /srv/salt`
1. Pull any config changes `git pull`
1. Run the salt highstate `salt linkwebv3 state.highstate`

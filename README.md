# tirreno for WordPress

Improve your WordPress security by detecting account threats and abuse through integration with the tirreno security framework.

This plugin sends security events from WordPress to your tirreno instance to keep an audit log, detect threats and record field edit history. It covers logins and failed logins, logouts, registrations, password and email changes, profile, content and comment edits, searches and error pages. tirreno scores each account against its rules, so attacks such as credential stuffing, account takeover, spam and scanning can be reviewed in the tirreno console.

## How to use this plugin

1. Install WordPress.
2. Install tirreno by following the [setup instructions](https://github.com/tirrenotechnologies/DEVELOPMENT.md).
3. Upload the tirreno plugin to your WordPress site and activate it.
4. Open the tirreno plugin settings and paste in the `Tracking ID` and `Sensor URL` from your tirreno deployment.
5. Set up rules for anomaly detection in the tirreno console.

### tirreno console

Follow the [tirreno](https://www.tirreno.com) [setup instructions](https://github.com/tirrenotechnologies/DEVELOPMENT.md).

## License

Released under the [BSD 3-Clause License](https://opensource.org/license/bsd-3-clause).

tirreno is a registered trademark of tirreno technologies sàrl, Switzerland.

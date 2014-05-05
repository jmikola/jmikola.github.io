---
title: Dumping the Verizon FiOS Actiontec Router
tags:
    - verizon
    - fios
    - actiontec
    - networking
---
Hoboken is blessed with some decent options when it comes to internet service.
Verizon started wiring up most of the town with FiOS back in 2009. This was
around the same time that I noticed Cablevision was hitting capacity problems
with its distribution nodes, which are the drop points where fiber is converted
to coax lines. A recurring symptom of reduced bandwidth in the evening hours
hinted at overpopulated (read: oversold) nodes. Rather than wait for Cablevision
to upgrade its infrastructure, I jumped over to FiOS as soon as it became
available.

Each FiOS installation includes an ONT that converts the fiber-optic cable to
coax or Ethernet. Using Ethernet limits you to just internet service, but that's
not a problem at all for [cord cutters][cordcutters]; however, since my building
was already wired for coax and I wasn't keen on hanging Cat5 cable from my
apartment window down to the meter room, I went with coax and Verizon's bundled
Actiontec MI424WR router.

With FiOS, the MI424WR serves double duty as a [MoCa] bridge, converting coax
to usable Ethernet, and router. As far as routers go, it's not too bad. There's
actually quite a lot of functionality buried behind all of the "Are you sure you
want to see the advanced options?" prompts in its admin GUI. The MI424WR's
diminutive [NAT table][nat-issue] is a common complaint, but I haven't been
affected by it.

Everything was well and good until I stumbled upon my wireless network's SSID
and encryption key on Verizon's account management page:

![][verizon-account]

<small><em>Note: if it wasn't obvious by the SSID and WEP encryption, I took
this screenshot after a router reset.</em></small>

Clicking "UnHide" did exactly what you might expect: my encryption key was
revealed in plain text. And if you're curious, the button itself is nothing more
than a bit jQuery that fills in the actual key from a `js_passcode` variable
defined in the page source. I'm a bit curious if a creative encryption key could
be used to exploit an XSS vulnerability, but not enough to actually test it out.

It's obvious that Verizon intended this as some sort of convenience for their
customers (likely the type prone to writing their passwords on a post-it note),
but  this should be a huge red flag for anyone with the slightest concern for
privacy and security. I'm not sure when this "feature" was added, and I seldom
log in to my account page, but I don't recall seeing it before.

Of course, I wasn't the only one that noticed:

<blockquote>
<p>When all it takes to reset everything to factory settings for the average
brain-dead customer who has forgotten their password or key is to hold the
"reset" button for 15 seconds, what possible <em>reasonable</em> justification
for this level of intentional security hole is there?</p>
<footer>Layla Mah in <cite><a href="http://robot.laylamah.com/?p=63">Verizon Fios / Actiontec MI424WR Routers Insecure</a></cite></footer>
</blockquote>

Following the rabbit trail, I came across a lot of discussion about port 4567
and the [TR-069] protocol:

 * [Verizon's access to the router's WPA password][vz-access]
 * [Verizon should not be able to access user private information in routers][vz-idea]
 * [Verizon accessed my router (port 4567, TR-069)][dsl-access]
 * [Verizon changing users' router passwords][slashdot-vz-pw]
 * [Port 4567 open on Actiontec router using Verizon FiOS][compu-help]
 * [Remove the Actiontec Verizon backdoor on port 4567][dsl-block-port]

There was also a [captivating tale][vz-hacked] involving Czech botnets, but
that involved a [Westell 9100] router, a less-secure predecessor to the modern
Actiontec models.

So, Verizon officially uses this to push firmware and security updates. I assume
it's also what they use to pull the router's SSID and encryption key. At this
point, I decided to do what I should have done years ago and replace the
Actiontec MI424WR with my own router.

Back when I had a cable modem, I adored my Linksys WRT54G. This is the classic
series that lead to [DD-WRT], [OpenWrt], [Tomato], and countless other
open-source firmwares. A couple of years ago, Rasmus [tweeted][rasmus-wrt] about
picking up a bunch of WRT160N routers at a great price, so I snap-bought one,
flashed it with custom firmware, and set it up as a repeater for my now-insecure
wifi network. It did lack the iconic black and blue body of its ancestors, but
802.11n was a welcome upgrade.

Step one of the MI424WR replacement process was heading over to
[DSLReports' FiOS FAQ][dsl-fios-faq], which is an amazing source of information.
I read a few articles about single-purpose MoCa bridges, but apparently not
enough, as I ended up purchasing an [Actiontec ECB2500][actiontec-moca] that
was completely unsuitable for my use case (thanks, Wei). I found this out only
after calling FiOS' tech support and getting re-routed to some private Actiontec
support line intended for Verizon technicians. If you do go this route, you'll
want to (a) read the FAQ in its entirety and (b) pick up either a Netgear
MCAB1001 or D-Link DXN-221. These are sold in pairs (unless you can find a
DXN-220), so I'd suggest going halfsies with a friend.

After getting rid of the useless Actiontec ECB2500, I was a bit apprehensive
about springing for a Netgear or D-Link. Both are increasingly hard to come by,
and they don't come cheap. Thankfully, I found an [old thread][dsl-bridge]
(from 2007!) in the DSLReports forums with instructions on turning the MI424WR
into a MoCa bridge. To paraphrase, the steps are as follows:

 1. Connect to the MI424WR via Ethernet and perform a factory reset from the
    *Advanced* section of the admin GUI.
 2. Log back in to the router using the default "admin" and "password"
    credentials, and access *Network Connections* under *My Network*.
 3. Access the settings for *Broadband Connection (Coax)* and ensure the privacy
    option is selected. Release the MI424WR's IP address and immediately change
    the *Internet Protocol* option to "No IP address" to prevent a new IP from
    being requested.
 4. Access the settings for *Network (Home/Office)* and enable *Broadband
    Connection (Coax)* under the list of bridged connections. The original guide
    referred to a STP checkbox, but I never saw it listed for the coax
    connection.
 5. Disable the *Wireless Access Point* network interface completely and remove
    it from any bridge configuration.
 6. If necessary, change the *Firewall Settings* to the minimum setting.
 7. At this point, the main page of the admin GUI should complain that it has no
    internet connection, but *Broadband Connection (Coax)* should still show up
    as "connected" in the list of network interfaces.
 8. Configure your preferred router with an IP address other than 192.168.1.1
    and connect its WAN port to the MI424WR's LAN port. The router should then
    be able to pick up a new Verizon IP address via DHCP.

Once the MI424WR is in bridge mode, its admin GUI will be inaccessible unless
you connect directly to one of its LAN ports with a static IP address in the
same subnet. As an added benefit, open port 4567 should no longer be an issue.
For FiOS TV customers with a STB, the [original thread][dsl-bridge] I referenced
has since been superseded with a [new guide][dsl-bridge-vod], which includes
additional instructions for ensuring compatibility with VOD services.

![][fios-wrt160n]

When the bridge up and running, there was just one more thing to fix. My MI424WR
had been configured so long ago that I completely forgot about Verizon's
dreadful DNS servers, which like to resolve non-existent domains to
[search.dnsassist.verizon.net][vz-dnsassist] and conveniently load up a page
full of Yahoo search results and targeted ads. Thankfully, I can rely on
Google's DNS servers to [behave properly][google-nxdomain].

  *[DHCP]: Dynamic host configuration protocol
  *[LAN]: Local area network
  *[MoCa]: Multimedia over coax alliance
  *[ONT]: Optical network terminal
  *[GUI]: Graphical user interface
  *[SSID]: Service set identifier
  *[STB]: Set-top box
  *[STP]: Spanning tree protocol
  *[VOD]: Video on demand
  *[WAN]: Wide area network
  *[WEP]: Wireless encryption protocol
  *[WPA]: Wifi protected access
  *[XSS]: Cross-site scripting

  [actiontec-moca]: http://www.newegg.com/Product/Product.aspx?Item=N82E16833996262
  [cordcutters]: http://www.reddit.com/r/cordcutters
  [DD-WRT]: http://dd-wrt.com/
  [dsl-block-port]: http://www.dslreports.com/forum/r21990593-modemrouter-Remove-the-actiontec-verizon-backdoor-on-port-456
  [dsl-access]: http://www.dslreports.com/forum/r19419191-Verizon-Accessed-My-Router-Port-4567-TR069
  [dsl-fios-faq]: http://www.dslreports.com/faq/verizonfios
  [dsl-bridge]: http://www.dslreports.com/forum/r17679150-Howto-make-ActionTec-MI424WR-a-network-bridge
  [dsl-bridge-vod]: http://www.dslreports.com/forum/r20006536-Make-your-actiontec-a-bridge-with-VOD-working-with-REV-D
  [google-nxdomain]: https://developers.google.com/speed/public-dns/faq#nxdomains
  [nat-issue]: http://www.dslreports.com/faq/16233
  [OpenWrt]: https://openwrt.org/
  [rasmus-wrt]: https://twitter.com/rasmus/status/222952956482420736
  [Tomato]: http://www.polarcloud.com/tomato
  [TR-069]: http://en.wikipedia.org/wiki/TR-069
  [vz-access]: http://forums.verizon.com/t5/FiOS-Internet/Verizon-s-Access-to-the-router-s-WPA-password/td-p/628243
  [vz-dnsassist]: http://search.dnsassist.verizon.net/
  [vz-idea]: http://forums.verizon.com/t5/Share-Your-Ideas-with-Verizon/Verizon-should-not-be-able-to-access-user-private-information-in/idi-p/666453
  [vz-hacked]: http://forums.verizon.com/t5/FiOS-Internet/Guy-accessed-remote-administration-port-4567-on-my-router-Thanks/td-p/241017
  [Westell 9100]: http://blogs.n1zyy.com/n1zyy/2009/11/23/fios-and-the-westell-9100/
  [compu-help]: http://www.compu-help.us/205.htm
  [slashdot-vz-pw]: http://tech.slashdot.org/story/10/08/01/1845234/Verizon-Changing-Users-Router-Passwords

  [verizon-account]: /images/20140505-verizon_account.png {.img-responsive .center-block}
  [fios-wrt160n]: /images/20140505-fios_wrt160n.jpg {.img-responsive .img-rounded .center-block}

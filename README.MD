# Image Linker Component
A Joomla! component for scanning and fixing broken image links in articles by matching them to files in the media folder.

Are you tired of unused images cluttering up your media folder and taking up space? This component scans your content, identifies missing images, and displays unlinked images, making it easy to keep your files tidy.

## How To Use Image Linker Component
1. Install the component via the Joomla! Extensions Manager. You can install via URL by using this URL: [https://github.com/brettvac/imagelinker/releases/download/1.0/com_imagelinker.zip](https://github.com/brettvac/imagelinker/releases/download/1.0/com_imagelinker.zip)
2. Navigate to Components > Image Linker Component in the Joomla! administrator.
3. Select the media folders to scan, enable case-sensitive matching if desired, and click "Scan for Broken Images."
4. Review the list of broken images, select replacement images from the suggested matches, and click "Fix All Images" to update the articles.

## Features
- **Folder Selection**: Choose specific media folders to scan, so you can exclude those used by other components (e.g., HikaShop, PhocaDownload).
- **Skip External Links**: Skips over malformed or external URLs and only scans for internal images
- **Secure Updates**: Fixes images using Joomla’s Table class, respecting article checkout/check-in.
- **Error Handling**: Gracefully skips invalid entries with try-catch blocks and displays detailed success or error messages.

## Requirements
This component works with Joomla! versions 4.0 or later and requires PHP 7.4 or later.

## FAQ
**Q: Can this component fix non-image links, like PDFs?**
**A:** No, it’s designed specifically for images (jpg, jpeg, png, gif) in the media folder (default images, but you can change in the configuration).

**Q: Where does this component look for unlinked images?**
**A:** This component scans for images which are not referenced in articles, modules, categories, banners, contacts, newsfeeds, menu items, custom fields, tags, or user profiles.

**Q: This component is awesome! Can I send a donation?**  
**A:** Sure! Send your cryptonation to the following wallets:

`BTC 1PXWZJcBfehqgV25zWdVDS6RF2yVMxFkZD`

`Eth 0xC9b695D4712645Ba178B4316154621B284e2783D`

**Q: Got any more awesome Joomla! extensions?**  
**A:** Find them [right here](https://naftee.com)

# Image Linker Component
Image Linker is a Joomla! Administrator component for scanning unlinked or broken images inside Joomla! core extensions and optionally deleting them.

## Why Use The Image Linker Component
Are you tired of unused images cluttering up your media folder and taking up space? 
This component scans the database for and searches inside your content, identifies missing images, and displays unlinked images.
Once the images are found, you have the option to delete unlinked images, making it easy to keep your images directory nice and tidy.

## Features
- **Granular Folder Selection**: Choose specific media folders to scan, so you can exclude those used by other components (e.g., HikaShop, PhocaDownload).
- **Local Files Only**: Skips over malformed or external URLs and only scans for internal images
- **Supports Native Joomla! Components**: Scans 11 common Joomla! components for image inclusion, such as banners, users and module content.
- **Detailed Error Handling**: Gracefully skips invalid entries and displays detailed success or error messages.

## How To Use Image Linker Component
1. Install the component via the Joomla! Extensions Manager, by using this URL: [https://github.com/brettvac/imagelinker/releases/latest/download/com_imagelinker.zip](https://github.com/brettvac/imagelinker/releases/latest/download/com_imagelinker.zip)
2. Navigate to Components > Image Linker in the Joomla! administrator backend.
3. Select the media folders you'd like to scan, enable case-sensitive matching if desired, and click the scan button.
4. Review the list of broken images, select those which you want to delete, and click the delete button.

### Requirements
This component works with Joomla! versions 5.0.0 or later and requires PHP versions 8.1 or later.

## FAQ
**Q: Can this component fix non-image links, like PDFs?**
**A:** No, it’s designed specifically for images (jpg, jpeg, png, gif) in the media folder (default images, but you can change in the configuration).

**Q: Where does this component look for unlinked images?**
**A:** This component scans for images which are not referenced in articles, modules, categories, banners, contacts, newsfeeds, menu items, custom fields, tags, or user profiles.

**Q: This component is awesome! Can I send a donation?**  
**A:** Sure! Send your cryptonation to the following wallets:

`BTC 1PXWZJcBfehqgV25zWdVDS6RF2yVMxFkZD`

`Eth 0xC9b695D4712645Ba178B4316154621B284e2783D`

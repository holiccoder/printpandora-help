---
external_id: 115001962143
title: 'How do I work with transparency in Illustrator for printing & saving?'
slug: 115001962143-How-do-I-work-with-transparency-in-Illustrator-for-printing-saving
section_external_id: 200572464
category: 'Your designs'
section: 'Design information'
locale: en-us
position: 11
promoted: false
vote_sum: 8
source_url: 'https://support.moo.com/hc/en-us/articles/115001962143-How-do-I-work-with-transparency-in-Illustrator-for-printing-saving'
remote_created_at: '2017-04-18T21:23:40+00:00'
remote_updated_at: '2025-10-10T19:35:05+00:00'
---

"Transparency" in artwork refers to elements that are not fully opaque, such as drop shadows or blending modes, enriching designs but requiring careful handling to avoid printing issues. "Flattening" converts transparent artwork for formats or printers that don't support transparency, balancing vector and raster elements. Illustrator offers flattening presets to control this process and a Flattener Preview tool to see affected areas. Specific objects can also be flattened using Object Flatten Transparency with preferred settings and saved as PDF/X-1a:2001.


### What does “transparency” mean in artwork, and why is it important?

“Transparency” refers to elements in your artwork that aren’t fully opaque—things like drop shadows, blending modes, partially transparent images, or vector objects with reduced opacity. These effects make designs richer, but not all file formats or printing systems handle transparency the same way. If transparency isn’t handled properly, you may see unexpected results like clipping, odd color shifts, or unwanted rasterization (turning parts of vector art into pixels).

### What is “flattening,” and when is it needed?

Flattening is the process of converting transparent artwork so that it can be printed or saved in formats that don’t fully support transparency. Illustrator breaks transparent artwork into parts that remain vector (sharp, scalable) and parts that are rasterized (turned into pixels) depending on complexity.

### What are “flattening presets” and how do I set them up?

Flattening presets let you control **how** transparency gets flattened — how much is rasterized vs. kept vector, resolution for rastered areas, what happens to strokes/text over transparent objects, and so on. You can use built-in presets or create your own.

Here’s how to set them up:

1. In Illustrator, go to **Edit &gt; Transparency Flattener Presets…**
2. Either pick a default preset (High Resolution, Medium, or Low depending on your output) or click **New** to create one.
3. Define settings like how vector vs raster is balanced, how strokes/text are treated, resolution of rasterized parts, etc.
4. Save your preset so you can reuse it for other documents or share it with others. [Adobe Help Center](https://helpx.adobe.com/illustrator/using/printing-saving-transparent-artwork.html)


### How do I preview which parts of my artwork will be affected by flattening?

Illustrator gives you a tool called **Flattener Preview** so you can see what parts of your artwork will be rasterized or otherwise altered when flattened:

- Open it via **Window &gt; Flattener Preview**. [Adobe Help Center](https://helpx.adobe.com/illustrator/using/printing-saving-transparent-artwork.html)
- In that panel, you can choose different modes to highlight affected areas (transparent objects, areas overlapping transparent ones, etc.).
- You can play with settings to see how changing your flattening preset alters which parts get rasterized or outlined. [Adobe Help Center](https://helpx.adobe.com/illustrator/using/printing-saving-transparent-artwork.html)

### How do I flatten transparency for a specific object?

If you only want to flatten one object (not the whole document), here’s how:

1. **Select** &gt; **All**
2. Use **Object &gt; Flatten Transparency…** in the menu.
3. Change default settings to our MOO preferred settings. They should look like this:![Screen_Shot_2017-04-18_at_5.18.11_PM.png](https://support.moo.com/hc/article_attachments/29834273504148)
4. Click **OK** to apply to just that object.
5. **Save as**... &gt; **PDF**
6. Choose the **PDF/X-1a:2001** from the first dropdown.![Screen_Shot_2017-04-18_at_5.21.40_PM.png](https://support.moo.com/hc/article_attachments/29834273504404)


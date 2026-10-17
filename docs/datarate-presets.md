# Film Transfer Toolkit - data rate / storage presets

Presets version 1.0.0, rates checked 2026-10-16. Generated from `core/data/datarate-presets.json` by `node tools/presets-doc.js`; the same numbers drive `FTTCore.datarate` (JS) and `FTT_Datarate` (PHP).

## Sources

- `apple_prores`: [Apple ProRes White Paper (April 2022), Target Data Rates tables, pp. 19-21](https://www.apple.com/final-cut-pro/docs/Apple_ProRes_White_Paper.pdf)
- `youtube`: [YouTube Help: YouTube recommended upload encoding settings (answer 1722171)](https://support.google.com/youtube/answer/1722171)
- `apple_tn2162`: [Apple Technical Note TN2162: Uncompressed Y'CbCr Video in QuickTime Files ('2vuy', 'v210', 'v410' layouts)](https://developer.apple.com/library/archive/technotes/tn2162/_index.html)
- `loc_dpx`: [Library of Congress FDD000178: Digital Moving-Picture Exchange (DPX), Version 2.0 (SMPTE ST 268; 10-bit components filled into 32-bit words, 12-bit into 16-bit words)](https://www.loc.gov/preservation/digital/formats/fdd/fdd000178.shtml)
- `tiff6`: [TIFF Revision 6.0 (Adobe, 1992): uncompressed RGB, BitsPerSample 8 or 16](https://download.osgeo.org/libtiff/doc/TIFF6.pdf)
- `h264`: [ITU-T H.264 (AVC). File size = bitrate x time; the bitrate is your choice](https://www.itu.int/rec/T-REC-H.264)
- `h265`: [ITU-T H.265 (HEVC). File size = bitrate x time; the bitrate is your choice](https://www.itu.int/rec/T-REC-H.265)

## Apple ProRes target data rates (Mb/s)

Source: [Apple ProRes White Paper (April 2022), Target Data Rates tables, pp. 19-21](https://www.apple.com/final-cut-pro/docs/Apple_ProRes_White_Paper.pdf). Apple's 24p/30p/60p rows also cover 23.976/29.97/59.94 (Apple quotes 220 Mb/s for 422 HQ at 1920x1080 29.97). Rates not listed (e.g. 16 or 18 fps frame-for-frame film) are scaled linearly from the nearest listed rate; Apple's own table is linear in frame rate (176 x 30/24 = 220). Apple publishes no figure for 4096x3112, so ProRes is not offered at that size. GB/hr = Mb/s x 0.45.

| Frame size | fps | 422 Proxy | 422 LT | 422 | 422 HQ | 4444 (no alpha) | 4444 XQ (no alpha) |
|---|---|---:|---:|---:|---:|---:|---:|
| SD NTSC 720×486 | 24p | 10 | 23 | 34 | 50 | 75 | 113 |
| SD NTSC 720×486 | 30p | 12 | 29 | 42 | 63 | 94 | 141 |
| SD PAL 720×576 | 25p | 12 | 28 | 41 | 61 | 92 | 138 |
| HD 1920×1080 | 24p | 36 | 82 | 117 | 176 | 264 | 396 |
| HD 1920×1080 | 25p | 38 | 85 | 122 | 184 | 275 | 413 |
| HD 1920×1080 | 30p | 45 | 102 | 147 | 220 | 330 | 495 |
| HD 1920×1080 | 50p | 76 | 170 | 245 | 367 | 551 | 826 |
| HD 1920×1080 | 60p | 91 | 204 | 293 | 440 | 660 | 990 |
| 2K DCI 2048×1080 | 24p | 41 | 93 | 134 | 201 | 302 | 453 |
| 2K DCI 2048×1080 | 25p | 43 | 97 | 140 | 210 | 315 | 472 |
| 2K DCI 2048×1080 | 30p | 52 | 116 | 168 | 251 | 377 | 566 |
| 2K DCI 2048×1080 | 50p | 86 | 194 | 280 | 419 | 629 | 944 |
| 2K DCI 2048×1080 | 60p | 103 | 232 | 335 | 503 | 754 | 1131 |
| 2K full aperture 2048×1556 | 24p | 56 | 126 | 181 | 272 | 407 | 611 |
| 2K full aperture 2048×1556 | 25p | 58 | 131 | 189 | 283 | 425 | 637 |
| 2K full aperture 2048×1556 | 30p | 70 | 157 | 226 | 340 | 509 | 764 |
| 2K full aperture 2048×1556 | 50p | 117 | 262 | 377 | 567 | 850 | 1275 |
| 2K full aperture 2048×1556 | 60p | 140 | 314 | 452 | 679 | 1019 | 1528 |
| UHD 4K 3840×2160 | 24p | 145 | 328 | 471 | 707 | 1061 | 1591 |
| UHD 4K 3840×2160 | 25p | 151 | 342 | 492 | 737 | 1106 | 1659 |
| UHD 4K 3840×2160 | 30p | 182 | 410 | 589 | 884 | 1326 | 1989 |
| UHD 4K 3840×2160 | 50p | 303 | 684 | 983 | 1475 | 2212 | 3318 |
| UHD 4K 3840×2160 | 60p | 363 | 821 | 1178 | 1768 | 2652 | 3977 |
| 4K DCI 4096×2160 | 24p | 155 | 350 | 503 | 754 | 1131 | 1697 |
| 4K DCI 4096×2160 | 25p | 162 | 365 | 524 | 786 | 1180 | 1769 |
| 4K DCI 4096×2160 | 30p | 194 | 437 | 629 | 943 | 1414 | 2121 |
| 4K DCI 4096×2160 | 50p | 323 | 730 | 1049 | 1573 | 2359 | 3539 |
| 4K DCI 4096×2160 | 60p | 388 | 875 | 1257 | 1886 | 2828 | 4242 |

## YouTube recommended upload bitrates (Mb/s, H.264)

Source: [YouTube Help: YouTube recommended upload encoding settings (answer 1722171)](https://support.google.com/youtube/answer/1722171). Standard frame rate = 24/25/30; high = 48/50/60 (the calculator uses "high" at 48 fps and above). Where YouTube gives a range, the calculator sizes with the upper figure and shows the range.

| Resolution | SDR standard | SDR high | HDR standard | HDR high |
|---|---:|---:|---:|---:|
| 1080p | 8 | 12 | 10 | 15 |
| 1440p | 16 | 24 | 20 | 30 |
| 2160p (4K) | 35-45 | 53-68 | 44-56 | 66-85 |

YouTube also lists audio: mono 128 kbps, stereo 384 kbps, 5.1 512 kbps (the calculator offers AAC stereo 384 kbps).

## Image sequences: bytes per frame

DPX: [Library of Congress FDD000178: Digital Moving-Picture Exchange (DPX), Version 2.0 (SMPTE ST 268; 10-bit components filled into 32-bit words, 12-bit into 16-bit words)](https://www.loc.gov/preservation/digital/formats/fdd/fdd000178.shtml). RGB 8-bit = 3 bytes/pixel; 10-bit = 4 bytes/pixel (three 10-bit samples filled into one 32-bit word, packing method A); 12-bit and 16-bit = 6 bytes/pixel (12-bit samples in 16-bit words). Lines padded to 32-bit boundaries. Plus a 2048-byte header per file (the generic + industry header; files with user data or an 8 KB image offset are slightly larger).

TIFF: [TIFF Revision 6.0 (Adobe, 1992): uncompressed RGB, BitsPerSample 8 or 16](https://download.osgeo.org/libtiff/doc/TIFF6.pdf). Uncompressed RGB, 8 or 16 bits per sample (10/12-bit material is stored in 16-bit samples). Tag/IFD overhead (a few hundred bytes) ignored.

| Frame size | DPX 8 | DPX 10 | DPX 12 | DPX 16 | TIFF 8 | TIFF 16 |
|---|---:|---:|---:|---:|---:|---:|
| SD NTSC 720×486 | 1.05 MB | 1.40 MB | 2.10 MB | 2.10 MB | 1.05 MB | 2.10 MB |
| SD PAL 720×576 | 1.25 MB | 1.66 MB | 2.49 MB | 2.49 MB | 1.24 MB | 2.49 MB |
| HD 1920×1080 | 6.22 MB | 8.30 MB | 12.4 MB | 12.4 MB | 6.22 MB | 12.4 MB |
| 2K DCI 2048×1080 | 6.64 MB | 8.85 MB | 13.3 MB | 13.3 MB | 6.64 MB | 13.3 MB |
| 2K full aperture 2048×1556 | 9.56 MB | 12.7 MB | 19.1 MB | 19.1 MB | 9.56 MB | 19.1 MB |
| UHD 4K 3840×2160 | 24.9 MB | 33.2 MB | 49.8 MB | 49.8 MB | 24.9 MB | 49.8 MB |
| 4K DCI 4096×2160 | 26.5 MB | 35.4 MB | 53.1 MB | 53.1 MB | 26.5 MB | 53.1 MB |
| 4K full aperture 4096×3112 | 38.2 MB | 51.0 MB | 76.5 MB | 76.5 MB | 38.2 MB | 76.5 MB |

## Uncompressed video (QuickTime)

Source: [Apple Technical Note TN2162: Uncompressed Y'CbCr Video in QuickTime Files ('2vuy', 'v210', 'v410' layouts)](https://developer.apple.com/library/archive/technotes/tn2162/_index.html). v210 = 10-bit 4:2:2, 48 pixels in 128 bytes per line (widths pad up to a multiple of 48); 2vuy = 8-bit 4:2:2, 2 bytes/pixel; v410 = 10-bit 4:4:4, 4 bytes/pixel. Cross-check: Apple's ProRes white paper charts uncompressed 10-bit 4:2:2 1920x1080 29.97 at 1,326 Mb/s; the v210 formula gives 1325.78 Mb/s.

| Frame size | v210 @24 Mb/s | v210 @29.97 Mb/s | 2vuy @24 Mb/s | 2vuy @29.97 Mb/s | v410 @24 Mb/s | v410 @29.97 Mb/s |
|---|---:|---:|---:|---:|---:|---:|
| SD NTSC 720×486 | 179.2 | 223.7 | 134.4 | 167.8 | 268.7 | 335.6 |
| SD PAL 720×576 | 212.3 | 265.2 | 159.3 | 198.9 | 318.5 | 397.7 |
| HD 1920×1080 | 1061.7 | 1325.8 | 796.3 | 994.3 | 1592.5 | 1988.7 |
| 2K DCI 2048×1080 | 1141.3 | 1425.2 | 849.3 | 1060.6 | 1698.7 | 2121.2 |
| 2K full aperture 2048×1556 | 1644.3 | 2053.4 | 1223.7 | 1528.1 | 2447.4 | 3056.2 |
| UHD 4K 3840×2160 | 4246.7 | 5303.1 | 3185.0 | 3977.3 | 6370.1 | 7954.7 |
| 4K DCI 4096×2160 | 4565.2 | 5700.8 | 3397.4 | 4242.5 | 6794.8 | 8485.0 |
| 4K full aperture 4096×3112 | 6577.3 | 8213.4 | 4894.8 | 6112.3 | 9789.5 | 12224.6 |

## H.264 / H.265 / custom

File size = (video Mb/s + audio kb/s / 1000) x running time / 8. H.264 [ITU-T H.264 (AVC). File size = bitrate x time; the bitrate is your choice](https://www.itu.int/rec/T-REC-H.264) and H.265 [ITU-T H.265 (HEVC). File size = bitrate x time; the bitrate is your choice](https://www.itu.int/rec/T-REC-H.265) do not fix a bitrate; the customer or lab chooses it. Container overhead ignored.

## Running time, frame rates and units

- Film frames = feet x frames per foot (8mm 80, Super 8 72, 16mm 40, 35mm 4-perf 16, 3-perf 21.33, 2-perf 32), or running time x film speed. A full reel uses the nominal reel capacity (e.g. 7 in Super 8 = 400 ft).
- "Same as film speed" = frame-for-frame (e.g. 18 fps Super 8 delivered as an 18 fps file). A different output rate keeps the running time and adds or drops frames (e.g. 18 fps film delivered at 29.97 fps).
- GB/TB are decimal (10^9 / 10^12 bytes, as on drive labels); GiB/TiB are binary (2^30 / 2^40). Suggested drive = smallest of 128 GB, 256 GB, 500 GB, 1 TB, 2 TB, 4 TB, 5 TB, 8 TB, 10 TB, 12 TB, 14 TB, 16 TB, 18 TB, 20 TB, 22 TB, 24 TB that holds total x (1 + headroom), default headroom 20%; above 24 TB it suggests several 24 TB drives.

## Worked example (from the tests)

3 x 7 in Super 8 (400 ft, 18 fps, 26:40 each): ProRes 422 HQ HD at 24 fps = 176 Mb/s x 1600 s = 35.2 GB per reel; H.264 20 Mb/s frame-for-frame = 4.00 GB per reel. Total 118 GB (110 GiB); with 20% headroom it needs 141 GB -> 256 GB drive.

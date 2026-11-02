"""Verify actual TCPDF pagination and image positions; uses pdfplumber."""
import json
import sys
import pdfplumber

results = json.load(open(sys.argv[1]))
expected_counts = {'avery': [14, 1], 'partial': [1, 1], 'offset': [1, 1],
                   'brother': [2], 'png_override': [1]}
for name, path in results.items():
    with pdfplumber.open(path) as pdf:
        assert [len(page.images) for page in pdf.pages] == expected_counts[name], name
        if name != 'brother':
            assert all(abs(p.width - 612) < .01 and abs(p.height - 792) < .01 for p in pdf.pages)
        if name == 'avery':
            tops = [38.25, 142.85, 247.5, 352.1, 456.75, 561.35, 666]
            for index, image in enumerate(pdf.pages[0].images):
                assert abs(image['x0'] - (72 + index % 2 * 270 + 2 * 72 / 25.4)) < .01
                assert abs(image['top'] - (tops[index // 2] + 8.5 * 72 / 25.4)) < .01
            # A unique asset appears exactly once in its expected label, on page 2.
            assert 'L015' not in pdf.pages[0].extract_text()
            assert 'L015' in pdf.pages[1].extract_text()
        if name in ('partial', 'offset'):
            dx, dy = (1.2, -.8) if name == 'offset' else (0, 0)
            first = pdf.pages[0].images[0]
            second = pdf.pages[1].images[0]
            assert abs(first['x0'] - (342 + (2 + dx) * 72 / 25.4)) < .01
            assert abs(first['top'] - (666 + (8.5 + dy) * 72 / 25.4)) < .01
            assert abs(second['x0'] - (72 + (2 + dx) * 72 / 25.4)) < .01
        if name == 'brother':
            assert abs(pdf.pages[0].width - 595.276) < .01
            # Avery's skip/calibration must not affect the existing tape layout.
            assert abs(pdf.pages[0].images[0]['x0'] - 12 * 72 / 25.4) < .01
print('PDF pagination, sheet alignment, calibration and tape regression checks passed.')

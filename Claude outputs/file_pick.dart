import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../theme.dart';
import 'common.dart';

/// A photo or PDF the person chose (2026-11-07: message attachments, homework answers).
class PickedFile {
  const PickedFile(this.path, this.name, this.size);

  final String path;
  final String name;
  final int size;

  bool get isPdf => name.toLowerCase().endsWith('.pdf');
}

/// Photos (JPG, PNG, WEBP, HEIC) and PDFs, up to 10 MB — the same as the website.
Future<PickedFile?> pickPhotoOrPdf(BuildContext context) async {
  // file_picker 13 (static methods; FilePicker.platform was removed in 11.0.0).
  // In version 13, pickFiles returns the list of chosen files (empty when cancelled).
  final picked = await FilePicker.pickFiles(
    type: FileType.custom,
    allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'pdf'],
  );
  if (picked.isEmpty) return null;
  final f = picked.first;
  if (f.path == null) return null;
  // Measure the file ourselves (PlatformFile has no size in version 13).
  final size = await File(f.path!).length();
  if (size > 10 * 1024 * 1024) {
    if (context.mounted) showSnack(context, 'That file is bigger than 10 MB. Please choose a smaller one.', error: true);
    return null;
  }
  return PickedFile(f.path!, f.name, size);
}

/// The chosen file, with a button to take it off again.
class PickedFileChip extends StatelessWidget {
  const PickedFileChip({super.key, required this.file, required this.onRemove});

  final PickedFile file;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(10, 6, 4, 6),
      decoration: BoxDecoration(color: Mw.tealTint, borderRadius: BorderRadius.circular(12)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(file.isPdf ? Icons.picture_as_pdf_outlined : Icons.image_outlined, size: 18, color: Mw.teal),
          const SizedBox(width: 6),
          Flexible(child: Text(file.name, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, color: Mw.tealDark, fontSize: 13))),
          IconButton(
            visualDensity: VisualDensity.compact,
            tooltip: 'Remove',
            onPressed: onRemove,
            icon: const Icon(Icons.close_rounded, size: 18, color: Mw.ink500),
          ),
        ],
      ),
    );
  }
}

/// Shows a photo or file from a message or homework (signed link from the API).
class AttachmentView extends StatelessWidget {
  const AttachmentView({super.key, required this.attachment, this.onDark = false});

  /// {name, kind, size_label, url, previewable}
  final Map<String, dynamic> attachment;
  final bool onDark;

  @override
  Widget build(BuildContext context) {
    final url = '${attachment['url'] ?? ''}';
    if (attachment['previewable'] == true && url.isNotEmpty) {
      return GestureDetector(
        onTap: () => openLink(context, url),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxHeight: 240, maxWidth: 240),
            child: Image.network(
              url,
              fit: BoxFit.cover,
              errorBuilder: (_, _, _) => _fileRow(context, url),
              loadingBuilder: (c, child, p) => p == null ? child : const SizedBox(width: 160, height: 120, child: Center(child: CircularProgressIndicator(strokeWidth: 2))),
            ),
          ),
        ),
      );
    }
    return _fileRow(context, url);
  }

  Widget _fileRow(BuildContext context, String url) => InkWell(
        onTap: () => openLink(context, url),
        borderRadius: BorderRadius.circular(12),
        child: Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(color: onDark ? Colors.white.withValues(alpha: .15) : Colors.white, borderRadius: BorderRadius.circular(12), border: onDark ? null : Border.all(color: Mw.line)),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(attachment['kind'] == 'pdf' ? Icons.picture_as_pdf_outlined : Icons.image_outlined, color: onDark ? Colors.white : Mw.danger),
              const SizedBox(width: 8),
              Flexible(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text('${attachment['name'] ?? 'File'}', overflow: TextOverflow.ellipsis, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: onDark ? Colors.white : Mw.ink900)),
                    if (attachment['size_label'] != null)
                      Text('${attachment['size_label']}', style: TextStyle(fontSize: 11.5, color: onDark ? Colors.white70 : Mw.ink500)),
                  ],
                ),
              ),
            ],
          ),
        ),
      );
}

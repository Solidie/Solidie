import React, { useState } from "react";
import { message, Modal, Upload, Button } from "antd";
import { PlusOutlined, UploadOutlined } from "@ant-design/icons";

const UploadImage = React.forwardRef(({}, ref) => {
  const [fileList, setFileList] = useState([]);
  const [previewOpen, setPreviewOpen] = useState(false);
  const [previewImage, setPreviewImage] = useState("");
  const [previewTitle, setPreviewTitle] = useState("");

  const [uploading, setUploading] = useState(false);

  const handleUpload = () => {
    const formData = new FormData();
    fileList.forEach((file) => {
      formData.append("files[]", file);
    });
    setUploading(true);
    // You can use any AJAX library you like
    fetch("https://www.mocky.io/v2/5cc8019d300000980a055e76", {
      method: "POST",
      body: formData,
    })
      .then((res) => res.json())
      .then(() => {
        setFileList([]);
        message.success("upload successfully.");
      })
      .catch(() => {
        message.error("upload failed.");
      })
      .finally(() => {
        setUploading(false);
      });
  };

  const handleCancel = () => setPreviewOpen(false);

  const handlePreview = async (file) => {
    if (!file.url && !file.preview) {
      file.preview = await getBase64(file.originFileObj);
    }

    setPreviewImage(file.url || file.preview);
    setPreviewOpen(true);
    setPreviewTitle(
      file.name || file?.url?.substring(file?.url?.lastIndexOf("/") + 1)
    );
  };

  const uploadButton = (
    <div>
      <PlusOutlined />
      <div style={{ marginTop: 8 }}>Upload</div>
    </div>
  );

  const props = {
    onRemove: (file) => {
      const index = fileList.indexOf(file);
      const newFileList = fileList.slice();
      newFileList.splice(index, 1);
      setFileList(newFileList);
    },
    beforeUpload: (file) => {
      setFileList([...fileList, file]);

      return false;
    },
    fileList,
    onChange: ({ fileList: newFileList }) => setFileList(newFileList),
    listType: "picture",
    onPreview: handlePreview,
    maxCount: 1,
  };

  return (
    <>
      <Upload
        {...props}
        ref={ref}
        className={"!w-full [&_.ant-upload-list-picture]:!w-max !flex !flex-wrap  !space-y-3 sm:!space-y-0 sm:!space-x-4".classNames()}
        
      >
        <Button
          className={"py-3 px-7 rounded-full bg-primary text-tertiary font-bold hover:!text-tertiary hover:shadow-lg shadow-tertiary/60 border-transparent !border-2 hover:!border-solid hover:!border-2 hover:!border-tertiary h-max".classNames()}
          icon={<UploadOutlined />}
        >
          Upload (Max: 1)
        </Button>
      </Upload>
      <Modal
        open={previewOpen}
        title={previewTitle}
        footer={null}
        onCancel={handleCancel}
      >
        <img alt="example" style={{ width: "100%" }} src={previewImage} />
      </Modal>
    </>
  );
});

export default UploadImage;

const getBase64 = (file) =>
  new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = () => resolve(reader.result);
    reader.onerror = (error) => reject(error);
  });